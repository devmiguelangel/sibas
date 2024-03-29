@extends('layout')

@section('header')
    @include('partials.header-home')
@endsection

@section('menu-main')
    @include('partials.menu-main')
@endsection

@section('menu-header')
  <div class="page-header">
      <div class="page-header-content">
          <div class="page-title">
              <h4><i class="icon-arrow-left52 position-left"></i> <span class="text-semibold">Anulación de Pólizas</span></h4>
          </div>
      </div>

  </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <!-- Horizontal form -->
            <div class="panel panel-flat border-top-primary">
                <div class="clearfix"></div>
                
                <div class="panel-body">
                    <div class="col-xs-12">
                        {!! Form::open(['route' => ['de.cancel.lists', 'rp_id' => $rp_id], 'method' => 'get', 'class' => 'form-horizontal']) !!}
                          <div class="col-xs-12 col-md-12">
                            @include('report.partials.inputs-search')
                          </div>
                        {!! Form::close() !!}

                        <table class="table datatable-fixed-left table-striped" width="100%" ng-controller="CancellationController">
                          <thead>
                            <tr>
                              <th>Nro. de Póliza</th>
                              <th>Cliente</th>
                              <th>C.I.</th>
                              <th>Monto Solicitado</th>
                              <th>Moneda</th>
                              <th>Plazo del Crédito</th>
                              <th>Usuario</th>
                              <th>Sucursal / Agencia</th>
                              <th>Fecha de Ingreso</th>
                              <th>Certificados Anulados</th>
                              <th>Acción</th>
                            </tr>
                          </thead>
                          <tbody>
                            @foreach ($headers as $header)
                              @foreach ($header->details as $detail)
                                <tr>
                                  <td>{{ $header->prefix }}-{{ $header->issue_number }}</td>
                                  <td>{{ $detail->client->full_name }}</td>
                                  <td>{{ $detail->client->dni }} {{ $detail->client->extension }}</td>
                                  <td>{{ number_format($header->amount_requested, '2', '.', ',') }}</td>
                                  <td>{{ config('base.currencies.' . $header->currency) }}</td>
                                  <td>{{ $header->term }} {{ config('base.term_types.' . $header->type_term) }}</td>
                                  <td>{{ $header->user->full_name }}</td>
                                  <td>
                                    {{ ! is_null($header->user->city) ? $header->user->city->name : '' }}
                                    {{ ! is_null($header->user->agency) ? '/ ' . $header->user->agency->name : '' }}
                                  </td>
                                  <td>
                                    {{ $header->created_date }}
                                  </td>
                                  <td>{{ 'NO' }}</td>
                                  <td>
                                    <ul class="icons-list">
                                        <li class="dropdown">
                                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                                <i class="icon-menu9"></i>
                                            </a>
                                            <ul class="dropdown-menu dropdown-menu-right">
                                                <li>
                                                    <a href="{{ route('de.cancel.create', ['rp_id' => $rp_id, 'header_id' => encode($header->id)]) }}" 
                                                      ng-click="cancelCreate($event)">
                                                      <i class="icon-cancel-circle2"></i> Anular
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="{{route('create_modal_slip', ['id_retailer_product'=>$rp_id, 'id_header'=>encode($header->id), 'text'=>'issuance', 'type'=>'IMPR'])}}"
                                                       id="issuance" class="open_modal">
                                                        <i class="icon-plus2"></i> Ver Certificado de Emision
                                                    </a>
                                                </li>
                                            </ul>
                                        </li>
                                    </ul>
                                  </td>
                                </tr>
                              @endforeach
                            @endforeach
                          </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- /horizotal form -->
        </div>
    </div>

<!-- modal -->
@include('partials.modal_content_report')
<!-- /modal -->

@endsection