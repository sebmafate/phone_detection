<?php
include_file('core', 'authentification', 'php');

if (!isConnect('admin')) {
    throw new Exception('{{401 - Refused access}}');
}
// Inclure la feuille de style de la page
include_file('desktop', 'phone_detection', 'css', 'phone_detection');
// Obtenir l'identifiant du plugin
$plugin = plugin::byId('phone_detection');
// Charger le javascript
sendVarToJS('eqType', $plugin->getId());
// Accéder aux données du plugin
$eqLogics = eqLogic::byType($plugin->getId());
?>

<script type="text/javascript">
var deviceType = new Object();
<?php foreach($eqLogics as $eqLogic): ?>
  deviceType["<?php echo $eqLogic->getId(); ?>"] = "<?php echo $eqLogic->getConfiguration("deviceType"); ?>";
<?php endforeach; ?>
</script>
  <!-- Container global (Ligne bootstrap) -->
  <div class="row row-overflow">
    <!-- Container bootstrap du menu latéral -->
    <div class="col-lg-2 col-md-3 col-sm-4">
      <!-- Container du menu latéral -->
      <div class="bs-sidebar">
        <!-- Menu latéral -->
        <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
          <!-- Bouton d'ajout -->
          <a class="btn btn-default eqLogicAction" data-action="add" style="margin-bottom: 5px;width: 100%">
            <i class="fa fa-plus-circle"></i> {{Ajouter un objet}}
          </a>
          <!-- Filtre des objets -->
          <li class="filter" style="margin-bottom: 5px; width: 100%"><input class="filter form-control input-sm" placeholder="{{Rechercher}}"/></li>
          <!-- Liste des objets -->
            <?php foreach ($eqLogics as $eqLogic): ?>
              <li class="cursor li_eqLogic" data-eqLogic_id="<?php echo $eqLogic->getId(); ?>">
                <a><?php echo $eqLogic->getHumanName(true); ?></a>
              </li>
            <?php endforeach;?>
        </ul>
      </div>
    </div>
    <!-- Container des listes de commandes / éléments -->
    <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay">
      <legend><i class="fa fa-cog"></i> {{Gestion}}</legend>
      <div class="eqLogicThumbnailContainer">
        <!-- Bouton d'ajout d'un objet -->
        <div class="cursor eqLogicAction" data-action="add"
             style="background-color : #ffffff; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
          <i class="fa fa-plus-circle" style="font-size : 6em;color:#94ca02;"></i>
          <span
              style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02">{{Ajouter}}</span>
        </div>
        <!-- Bouton d'accès à la configuration -->
        <div class="cursor eqLogicAction" data-action="gotoPluginConf"
             style="background-color : #ffffff; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">

          <i class="fa fa-wrench" style="font-size : 6em;color:#767676;"></i>

          <span
              style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676">{{Configuration}}</span>
        </div>
      </div>
      <!-- Début de la liste des objets -->
      <legend><i class="fa fa-table"></i> {{Mes objects}}</legend>
      <!-- Container de la liste -->
	<style type="text/css">
	span.phone > span.label { display: inline !important; }
	</style>
      <div class="eqLogicThumbnailContainer">
        <!-- Boucle sur les objects -->
          <?php foreach ($eqLogics as $eqLogic): ?>
            <div class="eqLogicDisplayCard cursor" data-eqLogic_id="<?php echo $eqLogic->getId(); ?>"
                 style="position: absolute; left: 0px; top: 0px;">

            <?php if ($eqLogic->getConfiguration('deviceType') == 'phone'): ?>
            <span style="color:white; margin: 0 auto; background-color:deepskyblue; width:75px !important;height:75px !important; border-radius:16px;">
              <img src="/plugins/phone_detection/desktop/images/white_single_phone.png" style="height:60px !important; width: auto !important; padding-top:15px !important;max-height:auto !important;min-height:auto !important;" />
            </span>
            <?php else: ?>
            <span style="color:white; margin: 0 auto; background-color:red; width:75px !important;height:75px !important; border-radius:16px;">
              <img src="/plugins/phone_detection/desktop/images/white_phone_group.png" style="height:60px !important; width: auto !important; padding-top:15px !important;max-height:auto !important;min-height:auto !important;" />
            </span>
            <?php endif ?>
	      <!-- <i class="fa fa-mobile" style="font-size : 6em;color:white; margin: 0 auto; padding: 12pt 8pt; background-color:deepskyblue; width:75px !important;height:75px !important; border-radius:16px;"></i> -->
	      <span class="name phone" style="display:inline-block">
			<?php echo $eqLogic->getHumanName(true, true); ?>
	      </span>
            </div>
          <?php endforeach;?>
      </div>
    </div>
    <!-- Container du panneau de contrôle -->
    <div class="col-lg-10 col-md-9 col-sm-8 eqLogic"
         style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
      <!-- Bouton sauvegarder -->
      <a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i>
        {{Sauvegarder}}</a>
      <!-- Bouton Supprimer -->
      <a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i>
        {{Supprimer}}</a>
      <!-- Bouton configuration avancée -->
      <a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i>
        {{Configuration avancée}}</a>
      <!-- Liste des onglets -->
      <ul class="nav nav-tabs" role="tablist">
        <!-- Bouton de retour -->
        <li role="presentation"><a class="eqLogicAction cursor" aria-controls="home" role="tab"
                                   data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a>
        </li>
        <!-- Onglet "Equipement" -->
        <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab"
                                                  data-toggle="tab"><i
                class="fa fa-mobile"></i> {{Equipement}}</a></li>
        <!-- Onglet "Commandes" -->
        <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i
                class="fa fa-list-alt"></i> {{Commandes}}</a></li>
      </ul>
      <!-- Container du contenu des onglets -->
      <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
        <!-- Panneau de modification de l'objet -->
        <div role="tabpanel" class="tab-pane active" id="eqlogictab">
          <!-- Car le CSS, c'est pour les faibles -->
          <br/>
          <!-- Ligne de contenu -->
          <div class="row">
            <!-- Division en colonne -->
            <div class="col-sm-7">
              <!-- Début du formulaire -->
              <form class="form-horizontal">
                <!-- Bloc de champs -->
                <fieldset>
                  <!-- Container global d'un champ du formulaire -->
                  <div class="form-group">
                    <!-- Label du champ -->
                    <label class="col-sm-6 control-label">{{Nom de l'équipement}}</label>
                    <!-- Container du champ -->
                    <div class="col-sm-6">
                      <!-- Iidentifiant caché. -->
                      <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;"/>
                      <!-- Nom de l'objet-->
                      <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"/>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="col-sm-6 control-label" >{{Objet parent}}</label>
                    <div class="col-sm-6">
                      <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                        <option value="">{{Aucun}}</option>
                        <?php
foreach (jeeObject::all() as $object) {
    echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
}
?>
                      </select>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="col-sm-6 control-label">{{Etat}}</label>
                    <div class="col-sm-6">
                      <!-- Case à cocher activant l'équipement -->
                      <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
                      <!-- Case à cocher pour rendre l'élément visible -->
                      <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
                    </div>
                  </div>
                  <div class="form-group" id='phone_detection_macAddress'>
                    <!-- Label du champ -->
                    <label class="col-sm-6 control-label">{{Adresse MAC}}</label>
                    <!-- Container du champ -->
                    <div class="col-sm-6">
                      <!-- Nom de l'objet-->
                      <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="macAddress" placeholder="{{Adresse MAC}}"/>
                    </div>
                  </div>
                </fieldset>
              </form>
            </div>
          </div>
        </div>
        <!-- Panneau des commandes de l'objet -->
        <div role="tabpanel" class="tab-pane" id="commandtab">
          <!-- Bouton d'ajout d'une commande -->
          <!-- <a class="btn btn-success btn-sm cmdAction pull-right" data-action="add" style="margin-top:5px;"> <i
                class="fa fa-plus-circle"></i> {{Commandes}}</a>
          <br/><br/> -->
          <!-- Tableau des commandes -->
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
            <tr>
              <th style="width: 450px;">{{Nom}}</th>
              <!-- <th>{{Type}}</th> -->
              <th>{{Historique}}</th>
              <th>{{Actions}}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
<?php
include_file('core', 'plugin.template', 'js');
// Inclure le fichier javascript du phone_detection
include_file('desktop', 'phone_detection', 'js', 'phone_detection');
?>

<script type="text/javascript">
$('body').delegate('.eqLogicAttr[data-l1key=id]', 'change', function () {
  updateFormForDevice();
});


updateFormForDevice = function() {
  var deviceId = $('.eqLogicAttr[data-l1key=id]').value();
  deviceId = deviceId + "";

  // alert(deviceId + "\n" + deviceType[deviceId])

  if (deviceType[deviceId] != "GlobalGroup") {
    $("#phone_detection_macAddress").show();
    $(".btn[data-action=remove]").show();
    $("#bt_eqLogicConfigureRemove").show();
    $("#bt_eqLogicConfigureSave").removeClass("roundedRight");
  } else {
    $("#phone_detection_macAddress").hide();
    $(".btn[data-action=remove]").hide();
    $("#bt_eqLogicConfigureRemove").hide();
    $("#bt_eqLogicConfigureSave").addClass("roundedRight");
  }
}
</script>
