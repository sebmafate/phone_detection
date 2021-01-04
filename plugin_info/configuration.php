<?php

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

include_file('core', 'authentification', 'php');

if (!isConnect('admin')) {
    throw new Exception('{{401 - Refused access}}');
}

function getBTControllers()
{
    $result = shell_exec("hcitool dev | grep hci | awk -F ' ' '{ print $1,$2 }'");
    return explode("\n", $result);
}

?>


<form class="form-horizontal">
  <fieldset>
  <div class="form-group">
    <legend><i class="fas fa-list-alt"></i> {{Général}}</legend>
            <label class="col-sm-4 control-label">
                {{ Version plugin }} <sup><i class="fa fa-question-circle tooltips" title="{{C'est la version du plugin Phone_Detection}}" style="font-size : 1em;color:grey;"></i></sup>
            </label>
            <span style="top:6px;" class="col-sm-1"><?php echo phone_detection::getVersion(); ?></span>
        </div>
        </fieldset>
    <!-- <div class="form-group">
        <label class="col-sm-3 control-label">test</label>
        <div class="col-sm-3">
            <?php echo jeedom::getApiKey("phone_detection"); ?>
        </div>
    </div> -->
     <div class="form-group">
        <label class="col-lg-4 control-label">{{Pas de local}}</label>
        <div class="col-lg-3">
           <input type="checkbox" class="configKey" data-l1key="noLocal" />
       </div>
    </div>
    <div class="form-group">
        <label class="col-lg-4 control-label">{{Autoriser la mise a jour des fichiers des antennes automatiquement lors d'une mise a jour}} <sup><i class="fas fa-question-circle" title="{{Très pratique, mais attention si vos antennes ont des soucis au moment de la maj, alors il peut y avoir une roue crantée ii
nfinie et dans tous les cas la roue restera le temps de maj des antennes}}"></i></sup></label>
        <div class="col-lg-3">
           <input type="checkbox" class="configKey" data-l1key="allowUpdateAntennas" />
       </div>
    </div>
</fieldset>
</form>
<form class="form-horizontal">
    <fieldset>
    <legend><i class="icon loisir-darth"></i> {{Démon}}</legend>
         <div class="form-group">
    <label class="col-lg-4"></label>
    <div class="col-lg-8">
        <a class="btn btn-warning changeLogLive" data-log="logdebug"><i class="fas fa-cogs"></i> {{Mode debug forcé temporaire}}</a>
        <a class="btn btn-success changeLogLive" data-log="lognormal"><i class="fas fa-paperclip"></i> {{Remettre niveau de log local}}</a>
    </div>
    <br/>
    <br/>
    <label class="col-lg-4"></label>
    <div class="col-lg-8">
        <a class="btn btn-warning allantennas" data-action="update"><i class="fas fa-arrow-up"></i> {{Mettre a jour les fichiers sur toutes les antennes}}</a>
        <a class="btn btn-success allantennas" data-action="restart"><i class="fas fa-play"></i> {{Redémarrer toutes les antennes}}</a>
        <a class="btn btn-warning allantennas" data-action="updatedep"><i class="fas fa-arrow-up"></i> {{Mettre a jour les dépendances sur toutes lesantennes}}</a>
        <a class="btn btn-danger allantennas" data-action="stop"><i class="fas fa-stop"></i> {{Arrêter toutes les antennes}}</a>
    </div>
    </div>
    <div class="form-group">
        <label class="col-lg-4 control-label">{{Version Démon Local}}</label>
        <div class="col-lg-3">
           <span class="configKey" data-l1key="version" />
       </div>
    </div>
        <?php
            $remotes = phone_detection_remote::all();
            foreach ($remotes as $remote) {
                echo '<div class="form-group">';
                echo '<label class="col-lg-4 control-label">{{Version Démon }}' . $remote->getRemoteName() . '</label>';
                echo '<div class="col-lg-3">';
                echo '<span>' . $remote->getConfiguration('version','1.0') . '</span>';
                echo '</div>';
                echo '</div>';
            }
        ?>
       <div class="form-group">
        <label class="col-sm-4 control-label">{{Port clef bluetooth}}</label>
        <div class="col-sm-2">
            <select class="configKey form-control" data-l1key="port">
                <option value="none">{{Aucun}}</option>
                <?php
foreach (jeedom::getBluetoothMapping() as $name => $value) {
    echo '<option value="' . $name . '">' . $name . ' (' . $value . ')</option>';
}
?>
           </select>
       </div>
   </div>
</fieldset>
<fieldset>
    <div class="form-group">
      <label class="col-sm-4 control-label">
        {{ Contrôleur Bluetooth }}
      </label>
      <div class="col-sm-2">
        <select class="configKey form-control" data-l1key="btport">
          <option value="none">{{Aucun}}</option>
            <?php foreach (getBTControllers() as $name) {
                $device = explode(' ', $name);
                if ($name != "") {
                    echo '<option value="' . $device[0] . '">' . $device[0] . ' ['. $device[1] .']</option>';
                }
            }?>
        </select>
    </div>
    </fieldset>
    <fieldset>
    <div class="form-group">
      <label class="col-sm-4 control-label">
        {{ Intervalle de mise a jour quand le téléphone est absent }} <sup><i class="fa fa-question-circle tooltips" title="{{C'est le temps en secondes entre 2 tentatives de ping du téléphone quand le téléphone est absent}}" style="font-size : 1em;color:grey;"></i></sup>
      </label>
      <div class="col-sm-1">
        <input type="text" class="configKey form-control" data-l1key="interval" placeholder="10"/>
    </div>
    </fieldset>
    <fieldset>
    <div class="form-group">
      <label class="col-sm-4 control-label">
        {{ Intervalle de mise a jour quand le téléphone est présent }} <sup><i class="fa fa-question-circle tooltips" title="{{C'est le temps en secondes entre 2 tentatives de ping du téléphone quand le téléphone est présent}}" style="font-size : 1em;color:grey;"></i></sup>
      </label>
      <div class="col-sm-1">
        <input type="text" class="configKey form-control" data-l1key="present_interval" placeholder="30"/>
    </div>
    </fieldset>
    <fieldset>
    <div class="form-group">
      <label class="col-sm-4 control-label">
        {{ Délai pour considérer le téléphone comme absent }} <sup><i class="fa fa-question-circle tooltips" title="{{C'est le temps en secondes après lequel le téléphone est considéré comme absent}}" style="font-size : 1em;color:grey;"></i></sup>
      </label>
      <div class="col-sm-1">
        <input type="text" class="configKey form-control" data-l1key="absentThreshold" placeholder="180"/>
    </div>
    </div>
    <div class="form-group">
    <label class="col-lg-4 control-label">{{Port socket interne (modification dangereuse)}}</label>
    <div class="col-lg-1">
        <input class="configKey form-control" data-l1key="socketport" placeholder="{{55009}}" />
    </div>
    </div>
  </fieldset>
</form>
<script>
 $('.changeLogLive').on('click', function () {
     $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/phone_detection/core/ajax/phone_detection.ajax.php", // url du fichier php
            data: {
                action: "changeLogLive",
                level : $(this).attr('data-log')
            },
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) { // si l'appel a bien fonctionné
                if (data.state != 'ok') {
                    $('#div_alert').showAlert({message: data.result, level: 'danger'});
                    return;
                }
                $('#div_alert').showAlert({message: '{{Réussie}}', level: 'success'});
            }
        });
});

$('.allantennas').on('click', function () {
    if ($(this).attr('data-action') == 'update') {
        action = 'sendremotes';
    } else if ($(this).attr('data-action') == 'updatedep'){
        action = 'updateremotes';
    } else if ($(this).attr('data-action') == 'restart'){
        action = 'launchremotes';
    } else if ($(this).attr('data-action') == 'stop'){
        action = 'stopremotes';
    }
     $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/phone_detection/core/ajax/phone_detection.ajax.php", // url du fichier php
            data: {
                action: action
            },
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) { // si l'appel a bien fonctionné
                if (data.state != 'ok') {
                    $('#div_alert').showAlert({message: data.result, level: 'danger'});
                    return;
                }
                $('#div_alert').showAlert({message: '{{Réussie}}', level: 'success'});
            }
        });
});

function phone_detection_postSaveConfiguration(){
  $.ajax({
    type: "POST",
    url: "plugins/phone_detection/core/ajax/phone_detection.ajax.php",
    data: {
      action: "launchremotes",
    },
    dataType: 'json',
    global: false,
    error: function (request, status, error) {
      handleAjaxError(request, status, error);
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
        return;
      }
    }
  });
}
</script>

