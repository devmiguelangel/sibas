<?php

namespace Sibas\Repositories\De;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Sibas\Entities\De\Facultative;
use Sibas\Entities\De\Observation;
use Sibas\Entities\ProductParameter;
use Sibas\Repositories\BaseRepository;

class FacultativeRepository extends BaseRepository
{
    /**
     * @var ProductParameter
     */
    private $parameter = null;
    /**
     * @var array
     */
    protected $props = [
        'reason' => '',
        'state'  => '',
    ];

    /**
     * @param $user
     * @return mixed
     */
    public function getRecords($user)
    {
        $user_type = $user->profile->first()->slug;

        $fa = Facultative::with('detail.header.user', 'detail.client', 'observations.state');

        switch ($user_type) {
            case 'SEP':
                $fa->whereHas('detail.header', function ($query) use ($user) {
                        $query->where('ad_user_id', $user->id);
                        $query->where('type', 'I');
                    });
                break;
            case 'COP':
                $fa->whereHas('detail.header', function ($query) use ($user) {
                        $query->where('type', 'I');
                    })
                    ->where('state', 'PE');
                break;
        }

        $fa = $fa->orderBy('created_at', 'desc')->get();

        $this->records['all'] = $fa;

        $fa->each(function ($item, $key) use ($user_type) {
            // All
            if ($user_type === 'SEP') {
                if (! $item->read) {
                    $this->records['all-unread']->push($item);
                }
            } else {
                $this->records['all-unread']->push($item);
            }

            // Approved
            if ($item->state === 'PR' && $item->approved) {
                $this->records['approved']->push($item);

                if (! $item->read) {
                    $this->records['approved-unread']->push($item);
                }

                return true;
            }

            // Observed
            if ($item->observations->count() > 0) {
                $this->records['observed']->push($item);

                if (! $item->read) {
                    $this->records['observed-unread']->push($item);
                }

                return true;
            }

            // Rejected
            if ($item->state === 'PR' && ! $item->approved) {
                $this->records['rejected']->push($item);

                if (! $item->read) {
                    $this->records['rejected-unread']->push($item);
                }

                return true;
            }
        });

        return $this->records;
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function storeFacultative(Request $request, $rp_id)
    {
        $header          = $request['header'];
        $detail          = $request['detail'];
        $retailer        = $request['retailer'];
        $retailerProduct = $retailer->retailerProducts()->where('id', $rp_id)->first();

        if ($retailerProduct->facultative) {
            $this->getParameter($retailerProduct, $detail->amount, $detail->cumulus);

            $evaluation = $this->evaluation($detail);

            try {
                if ($detail->facultative instanceof Facultative) {
                    if ($evaluation) {
                        $detail->facultative()->update($this->props);

                        return true;
                    } else {
                        $detail->facultative()->delete();
                    }
                } else if ($evaluation) {
                    $detail->facultative()->create([
                        'id'     => date('U'),
                        'reason' => $this->props['reason'],
                        'state'  => $this->props['state'],
                    ]);

                    return true;
                }
            } catch (QueryException $e) {
                $this->errors = $e->getMessage();
            }
        }

        return false;
    }

    private function evaluation($detail) {
        if ($this->parameter instanceof ProductParameter) {
            switch ($this->parameter->slug) {
                case 'FC':

                    break;
                case 'AE':
                    return $this->setAeEvaluation($detail);
                    break;
                case 'FA':
                    return $this->setAeEvaluation($detail);
                    break;
            }
        }

        return false;
    }

    private function setAeEvaluation($detail)
    {
        $facultative = false;
        $response    = $this->getEvaluationResponse($detail->response);
        $imc         = $detail->client->imc;
        $reason      = '';

        if ($imc) {
            $reason .= str_replace([':name'], [$detail->client->full_name], $this->reasonImc) . '<br>';

            $facultative = true;
        }

        if ($response) {
            $reason .= str_replace([':name'], [$detail->client->full_name], $this->reasonResponse) . '<br>';

            $facultative = true;
        }

        if ($this->parameter->slug == 'FA') {
            $reason .= str_replace([':name', ':cumulus', ':amount_max'], [
                    $detail->client->full_name,
                    number_format($detail->cumulus, 2),
                    number_format(($this->parameter->amount_min - 1), 2)
                ], $this->reasonCumulus) . '<br>';

            $facultative = true;
        }

        if ($facultative) {
            $this->props['reason'] = $reason;
            $this->props['state']  = 'PE';
        }

        return $facultative;
    }

    private function getParameter($retailerProduct, $amount, $cumulus)
    {
        foreach ($retailerProduct->parameters as $parameter) {
            if (($amount >= $parameter->amount_min && $amount <= $parameter->amount_max)
                    || ($cumulus >= $parameter->amount_min && $cumulus <= $parameter->amount_max)) {
                $this->parameter = $parameter;
            }
        }
    }

    public function getFacultativeById($id)
    {
        $this->model = Facultative::with('detail.header.user', 'detail.client', 'observations')
            ->where('id', '=', $id)
            ->get();

        if ($this->model->count() === 1) {
            $this->model = $this->model->first();

            return true;
        }

        return false;
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function updateFacultative($request)
    {
        $user       = $request->user();
        $this->data = $request->all();

        $this->data['approved']  = (int) $this->data['approved'];
        $this->data['surcharge'] = (boolean) $this->data['surcharge'];

        $_obs = $this->data['observation'];

        if ($this->data['approved'] === 1 || $this->data['approved'] == 0) {
            $this->model->ad_user_id  = $user->id;
            $this->model->state       = 'PR';
            $this->model->observation = $_obs;

            if ($this->data['approved'] === 1) {
                $this->model->approved = true;

                if ($this->data['surcharge']) {
                    $this->model->surcharge    = true;
                    $this->model->percentage   = $this->data['percentage'];
                    $this->model->current_rate = $this->data['current_rate'];
                    $this->model->final_rate   = $this->data['final_rate'];
                } else {
                    $this->model->surcharge    = false;
                    $this->model->current_rate = $this->data['current_rate'];
                    $this->model->final_rate   = $this->data['final_rate'];
                }
            } else {
                $this->model->approved = false;
            }
        } elseif ($this->data['approved'] === 2) {
            $observation = new Observation([
                'id'          => date('U'),
                'ad_user_id'  => $user->id,
                'ad_state_id' => $this->data['state']['id'],
                'observation' => $_obs,
            ]);

            try {
                $this->model->observations()->save($observation);
            } catch (QueryException $e) {
                $this->errors = $e->getMessage();
            }
        }

        $this->model->read = false;

        return $this->saveModel();
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function storeAnswer($request, $id_observation)
    {
        $user       = $request->user();
        $this->data = $request->all();

        $this->model->observations()->update([
            'id'                   => $id_observation,
            'response'             => true,
            'observation_response' => $this->data['observation_response'],
            'date_response'        => new Carbon()
        ]);

        return $this->saveModel();
    }

}