<?php

namespace Sibas\Http\Controllers\De;

use Illuminate\Http\Response;
use Sibas\Entities\Client;
use Sibas\Http\Requests;
use Sibas\Http\Controllers\Controller;
use Sibas\Http\Requests\Client\ClientComplementFormRequest;
use Sibas\Http\Requests\Client\ClientCreateFormRequest;
use Sibas\Http\Requests\De\BalanceFormRequest;
use Sibas\Repositories\Client\ActivityRepository;
use Sibas\Repositories\Client\ClientRepository;
use Sibas\Repositories\De\DataRepository;
use Sibas\Repositories\De\DetailRepository;
use Sibas\Repositories\De\FacultativeRepository;
use Sibas\Repositories\De\HeaderRepository;
use Sibas\Repositories\Retailer\CityRepository;
use Sibas\Repositories\Retailer\RetailerProductRepository;

class DetailController extends Controller
{
    /**
     * @var DetailRepository
     */
    protected $repository;
    /**
     * @var HeaderRepository
     */
    protected $headerRepository;
    /**
     * @var ClientRepository
     */
    protected $clientRepository;
    /**
     * @var FacultativeRepository
     */
    protected $facultativeRepository;
    /**
     * @var DataRepository
     */
    protected $dataRepository;
    /**
     * @var CityRepository
     */
    protected $cityRepository;
    /**
     * @var ActivityRepository
     */
    protected $activityRepository;

    protected $reference;

    public function __construct(DetailRepository $repository,
                                HeaderRepository $headerRepository,
                                ClientRepository $clientRepository,
                                DataRepository $dataRepository,
                                CityRepository $cityRepository,
                                ActivityRepository $activityRepository,
                                FacultativeRepository $facultativeRepository)
    {
        $this->repository                = $repository;
        $this->headerRepository          = $headerRepository;
        $this->clientRepository          = $clientRepository;
        $this->dataRepository            = $dataRepository;
        $this->cityRepository            = $cityRepository;
        $this->activityRepository        = $activityRepository;
        $this->facultativeRepository     = $facultativeRepository;

        $this->reference = ['ISE', 'ISU'];
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Returns Data for Client register
     * @return array
     */
    public function getData()
    {
        return [
            'civil_status'  => $this->dataRepository->getCivilStatus(),
            'document_type' => $this->dataRepository->getDocumentType(),
            'gender'        => $this->dataRepository->getGender(),
            'cities'        => $this->cityRepository->getCitiesByType(),
            'activities'    => $this->activityRepository->getActivities(),
            'hands'         => $this->dataRepository->getHand(),
            'avenue_street' => $this->dataRepository->getAvenueStreet(),
        ];
    }

    /**
     * Show the form for creating a new Client.
     *
     * @param string $rp_id
     * @param string $header_id
     * @param null $client_id
     * @return Response
     */
    public function create($rp_id, $header_id, $client_id = null)
    {
        $data   = $this->getData();
        $client = new Client();

        if (! is_null($client_id) && $this->clientRepository->getClientById(decode($client_id))) {
            $client = $this->clientRepository->getModel();
        }

        return view('client.de.create', compact('rp_id', 'header_id', 'data', 'client'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  ClientCreateFormRequest $request
     * @param $rp_id
     * @param $header_id
     * @return Response
     */
    public function store(ClientCreateFormRequest $request, $rp_id, $header_id)
    {
        if ($this->headerRepository->getHeaderById(decode($header_id))) {
            $request['header'] = $this->headerRepository->getModel();

            if ($this->clientRepository->createClient($request)) {
                $request['client'] = $this->clientRepository->getModel();

                if ($this->repository->createDetail($request)) {
                    $detail = $this->repository->getModel();

                    return redirect()->route('de.question.create', [
                            'rp_id'     => $rp_id,
                            'header_id' => $header_id,
                            'detail_id' => encode($detail->id),
                        ])->with(['success_client' => 'La información del Cliente fue registrada']);
                }
            }
        }

        return redirect()->back()
            ->with(['error_detail' => 'El Cliente no pudo ser registrado'])
            ->withInput()->withErrors($this->repository->getErrors());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param $rp_id
     * @param $header_id
     * @param $detail_id
     * @return Response
     */
    public function edit($rp_id, $header_id, $detail_id)
    {
        if ($this->repository->getDetailById(decode($detail_id))) {
            $detail = $this->repository->getModel();

            if ($detail->client instanceof Client) {
                $client = $detail->client;
                $data   = $this->getData();

                return view('client.de.edit', compact('rp_id', 'header_id', 'detail_id', 'data', 'client'));
            }
        }

        return redirect()->back()->with(['error_client_edit' => 'El Cliente no existe']);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  ClientCreateFormRequest $request
     * @param $rp_id
     * @param $header_id
     * @param $detail_id
     * @return Response
     */
    public function update(ClientCreateFormRequest $request, $rp_id, $header_id, $detail_id)
    {
        if ($this->repository->getDetailById(decode($detail_id))) {
            $detail = $this->repository->getModel();

            if ($this->clientRepository->editClient($request, $detail->client)) {
                return redirect()->route('de.client.list', [
                    'rp_id'     => $rp_id,
                    'header_id' => $header_id
                ])->with(['success_client' => 'La información del Cliente se actualizó correctamente']);
            }
        }

        return redirect()->back()
            ->with(['error_client_edit' => 'La información del Cliente no puede ser actualizada'])
            ->withInput()->withErrors($this->repository->getErrors());
    }

    /**
     * Show the form for add complementary data.
     *
     * @param $rp_id
     * @param $header_id
     * @param $detail_id
     * @param null $ref
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function editIssue($rp_id, $header_id, $detail_id, $ref = null)
    {
        $ref = strtoupper($ref);

        if ($this->repository->getDetailById(decode($detail_id))) {
            $detail = $this->repository->getModel();

            if (in_array($ref, $this->reference)) {
                $data   = $this->getData();
                $client = $detail->client;

                if ($client instanceof Client) {
                    if ($ref === 'ISE') {
                        return view('client.de.detail-edit', compact('rp_id', 'header_id', 'ref', 'data', 'detail'));
                    } elseif (strtoupper($ref) === 'ISU') {
                        // return view('client.de.edit', compact('rp_id', 'header_id', 'data', 'client'));
                    }
                }
            }
        }

        return redirect()->back()
            ->with(['error_client' => 'La información del Cliente no puede ser editada']);
    }

    public function updateIssue(ClientComplementFormRequest $request, $rp_id, $header_id, $detail_id, $ref)
    {
        $ref = strtoupper($ref);

        if ($this->repository->getDetailById(decode($detail_id))) {
            $detail            = $this->repository->getModel();
            $request['detail'] = $detail;

            if (in_array($ref, $this->reference)) {
                if (($detail->client instanceof Client) && $this->clientRepository->updateIssueClient($request)) {
                    return redirect()->route('de.edit', [
                        'rp_id'     => $rp_id,
                        'header_id' => $header_id
                    ])->with(['success_client' => 'La información del Cliente se actualizó correctamente']);
                }
            };
        }

        return redirect()->back()
            ->with(['error_client' => 'La información del Cliente no pudo ser actualizada'])
            ->withInput()->withErrors($this->repository->getErrors());
    }

    public function editBalance($rp_id, $header_id, $detail_id)
    {
        if ($this->headerRepository->getHeaderById(decode($header_id))
                && $this->repository->getDetailById(decode($detail_id))) {
            $header = $this->headerRepository->getModel();
            $detail = $this->repository->getModel();

            return view('client.de.balance', compact('rp_id', 'header', 'detail'));
        }

        return redirect()->back()
            ->with(['error_detail' => 'El Saldo Deudor no puede ser editado']);
    }

    public function updateBalance(BalanceFormRequest $request, $rp_id, $header_id, $detail_id)
    {
        if ($this->headerRepository->getHeaderById(decode($header_id))) {
            $request['header'] = $this->headerRepository->getModel();

            if ($this->repository->getDetailById(decode($detail_id))) {
                $detail = $this->repository->getModel();

                if ($this->repository->updateBalance($request)) {
                    $request['detail']          = $detail;
                    $request['retailer'] = $request->user()->retailer->first();

                    $approved = true;
                    if ($this->facultativeRepository->storeFacultative($request, decode($rp_id))) {
                        $approved = false;
                    }

                    $this->repository->setApprovedDetail($approved);

                    return redirect()->route('de.edit', compact('rp_id', 'header_id'))
                        ->with(['success_detail' => 'El Saldo Deudor fue actualizado correctamente']);
                }
            }

        }

        return redirect()->back()
            ->with(['error_detail' => 'El Saldo Deudor no puede ser actualizado'])
            ->withInput()->withErrors($this->repository->getErrors());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

}
