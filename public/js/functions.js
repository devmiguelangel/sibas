/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * funcion carga modal por ajax
 * @param {type} id_header
 * @param {type} tokken
 * @param {type} url
 * @param {post} post
 * @param {type} type
 * @returns {undefined}
 */
function cargaModal(id_header, tokken, url, post, type) {
    $.ajax({
        url: url,
        type: post,
        data: {
            type: type,
            id_header: id_header,
            _token: tokken
        },
        dataType: 'JSON',
        beforeSend: function() {
            $("#respuesta").html('Buscando cliente...');
        },
        error: function() {
            $("#respuesta").html('<div> Ha surgido un error. </div>');
        },
        success: function(respuesta) {
            //console.log(respuesta.template_cert);
            if (respuesta) {
                $("#respuesta").html(respuesta.template_cert);
            } else {
                $("#respuesta").html('<div> No hay ningún cliente con ese id. </div>');
            }
        }
    });
}

/**
 * funcion imprime modal
 * @param {type} idDiv
 * @returns {undefined}
 */
function printSelec(idDiv) {
    var ficha = document.getElementById(idDiv);
    var ventimp = window.open(' ', 'popimpr');
    ventimp.document.write(ficha.innerHTML);
    ventimp.document.close();
    ventimp.print();
    ventimp.close();
    /* validacion ultimo paso, tickeado despues de imprimir*/
    /**if($('#last_level').class()=='current'){
       $('#last_level').removeClass('current'); 
       $('#last_level').addClass('first done');
    }/**/
}

/**
 * funcion retorna mensaje de exito al registro
 * @param {type} text
 * @returns {undefined}
 */
function messageAction(key, text) {
    
    if (key == 'succes') {
        $.jGrowl(text, {
            header: 'Regístro',
            life: 10000,
            theme: 'alert-styled-left alert-arrow-left border-lg alpha-teal text-teal-900'
        });
    } else if (key == 'info') {
        $.jGrowl(text, {
            header: 'Información',
            life: 10000,
            theme: 'alert-bordered alert-styled-left alert-info'
        });
    } else if (key == 'error') {
        $.jGrowl(text, {
            header: 'Error',
            life: 10000,
            theme: 'alert-bordered alert-styled-left alert-danger'
        });
    }
}

// validacion confirm
var FormGralF = {
    textDelConfirmDef: '¿Esta seguro de eliminar el registro?',
    deleteElement: function (url, text) {
        "use strict";
        text = (text == '') ? this.textDelConfirmDef : text;
        $('#md-colored .modal-footer').html('<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" onclick="window.location = \''+url+'\'" data-dismiss="modal">Aceptar</button>')
        $('#md-colored').modal();
    },
};
	
