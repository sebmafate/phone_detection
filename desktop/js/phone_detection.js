/*
* Permet la réorganisation des commandes dans l'équipement
*/
$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

/*
* Fonction permettant l'affichage des commandes dans l'équipement
*/
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = {configuration: {}};
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {};
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '    <td>';
  tr += '        <input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
  tr += '        <input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom}}">';
  tr += '    </td>';
  tr += '    <td>';
  tr += '        <span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
  tr += '        <span class="subType" subType="' + init(_cmd.subType) + '"></span>';
  tr += '    </td>';
  tr += '    <td>';
  tr += '        <label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label>';
  tr += '        <label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label>';
  tr += '    </td>';
  tr += '    <td>';
  tr += '       <span class="cmdAttr" data-l1key="htmlstate"></span>';
  tr += '    </td>';   
  tr += '    <td>';
  if (is_numeric(_cmd.id)) {
     tr += '     <a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
     tr += '     <a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
  }
  tr += '    </td>';
  tr += '</tr>';
  $('#table_cmd tbody').append(tr);
  var tr = $('#table_cmd tbody tr').last();
  jeedom.eqLogic.builSelectCmd({
    id:  $('.eqLogicAttr[data-l1key=id]').value(),
    filter: {type: 'info'},
    error: function (error) {
      $('#div_alert').showAlert({message: error.message, level: 'danger'});
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result);
      tr.setValues(_cmd, '.cmdAttr');
      jeedom.cmd.changeType(tr, init(_cmd.subType));
    }
  });
 }


$('#bt_remote_phone_detection').on('click', function () {
    $('#md_modal').dialog({title: "{{Gestion des antennes Bluetooth}}"});
    $('#md_modal').load('index.php?v=d&plugin=phone_detection&modal=phone_detection.remote&id=phone_detection').dialog('open');
});


$('#bt_health_phone_detection').on('click', function () {
    $('#md_modal').dialog({title: "{{Santé Détection des Téléphones}}"});
    $('#md_modal').load('index.php?v=d&plugin=phone_detection&modal=phone_detection.health&id=phone_detection').dialog('open');
});
