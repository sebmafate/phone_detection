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
        {{ Interval }} <sup><i class="fa fa-question-circle tooltips" title="{{C'est le temps en secondes entre 2 tentatives de ping du téléphone}}" style="font-size : 1em;color:grey;"></i></sup>
      </label>
      <div class="col-sm-1">
        <input type="text" class="configKey form-control" data-l1key="interval" placeholder="10"/>
    </div>
    </fieldset>
    <fieldset>
    <div class="form-group">
      <label class="col-sm-4 control-label">
        {{ Délai pour considéré le téléphone comme absent }} <sup><i class="fa fa-question-circle tooltips" title="{{C'est le temps en secondes après lequel le téléphone est considérer comme absent}}" style="font-size : 1em;color:grey;"></i></sup>
      </label>
      <div class="col-sm-1">
        <input type="text" class="configKey form-control" data-l1key="absentThreshold" placeholder="180"/>
    </div>
  </fieldset>
</form>
