<!--edw-->
@if(count($detail)==0)
<h2>Presione "Nuevo <i class="glyphicon glyphicon-plus "></i>" para registrar Riesgos.</h2>
@else
    <table class="table datatable-basic">
        <thead>
            <tr>
                <th>Materia </th>
                <th>Descripción</th>
                <th>Número</th>
                <th>Uso</th>
                <th>Valor Asegurado</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detail as $entity)
                <tr>
                    <td>{{ $entity->matter_insured }}</td>
                    <td>{{ $entity->matter_description }}</td>
                    <td>{{ $entity->number }}</td>
                    <td>{{ $entity->use }}</td>
                    <td>{{ $entity->insured_value }}</td>
                    <td>
                        <a href="#"
                            onclick="returnContent('{{ route('td.form.insured',['rp_id'=>$rp_id, 'header_id'=>decode($header_id), 'id_detail'=>$entity->id])}}', 'GET');$('.modal-title').html('Riesgo Asegurado')"
                            data-toggle="modal" data-target="#modal_general" class="btn btn-default pull-right">
                             Editar <i class="icon-pencil position-right"></i>
                         </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif