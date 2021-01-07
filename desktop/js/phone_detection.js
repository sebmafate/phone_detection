function addCmdToTable(_cmd) {
    // Test si le paramètre a été défini
    if (!isset(_cmd)) {
        // Initialise _cmd
        _cmd = {configuration: {}};
    }
    var row = ' \
            <tr class="cmd" data-cmd_id="' + init(_cmd.id) + '"> \
              <td> \
                <input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;"> \
                <input class="cmdAttr form-control input-sm" data-l1key="name"> \
              </td> \
              <td> \
                <span>\
                <label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label> \
              </span> \
              </td> \
              <td>\
                <a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> \
                <a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a> \
                <!--<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>--> \
              </td> \
            </tr>';
    // Ajoute la ligne au tableau
    $('#table_cmd tbody').append(row);
    // Ajoute l'identifiant de la commande à la balise <tr> de la ligne
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    // Initialise les fonctionnalités de la ligne
    jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));


  //   <td> \
  //   <span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span> \
  //   <span class="subType" subType="' + init(_cmd.subType) + '"></span> \
  // </td> \

}


$('#bt_remote_phone_detection').on('click', function () {
    $('#md_modal').dialog({title: "{{Gestion des antennes bluetooth}}"});
    $('#md_modal').load('index.php?v=d&plugin=phone_detection&modal=phone_detection.remote&id=phone_detection').dialog('open');
});


$('#bt_health_phone_detection').on('click', function () {
    $('#md_modal').dialog({title: "{{Sante Detection des Telephones}}"});
    $('#md_modal').load('index.php?v=d&plugin=phone_detection&modal=phone_detection.health&id=phone_detection').dialog('open');
});
