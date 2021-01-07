<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

if (!isConnect('admin')) {
	throw new Exception('401 Unauthorized');
}
$eqLogics = phone_detection::byType('phone_detection');
?>

<table class="table table-condensed tablesorter" id="table_healthopenenocean">
	<thead>
		<tr>
			<th>{{Phone}}</th>
			<th>{{ID}}</th>
			<th>{{Mac}}</th>
			<th>{{Present (Antenne)}}</th>
			<th>{{Présent}}</th>
			<th>{{Dernière communication}}</th>
			<th>{{Date création}}</th>
		</tr>
	</thead>
	<tbody>
	 <?php
foreach ($eqLogics as $eqLogic) {
	echo '<tr><td><a href="' . $eqLogic->getLinkToConfiguration() . '" style="text-decoration: none;">' . $eqLogic->getHumanName(true) . '</a></td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getId() . '</span></td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getConfiguration('macAddress') . '</span></td>';
	$states ='';
	$remotes = phone_detection_remote::all();
	foreach ($remotes as $remote){
		$name = $remote->getRemoteName();
		$statecmd = $eqLogic->getCmd('info', 'state_' . $name);
		if (is_object($statecmd)) {
			$antennaState = $statecmd->execCmd();
			$antennaName  = $name;
			$presence = 'success';
			if ($antennaState == 1) {
		            $states = $states . '<span class="label label-success" style="font-size : 0.9em;cursor:default;padding:0px 5px;">{{Present}} (' . ucfirst($antennaName) .')</span></br>';
		        } else {
		            $states = $states . '<span class="label label-danger" style="font-size : 0.9em;cursor:default;padding:0px 5px;">{{Absent}} (' . ucfirst($antennaName) .')</span></br>';
			}
		}	
	}
	$statecmd = $eqLogic->getCmd('info', 'state_local');
	if (is_object($statecmd)) {
		$antennaState = $statecmd->execCmd();
		$antennaName   = 'local';
	        if ($antennaState == 1) {
	            $states = $states . '<span class="label label-success" style="font-size : 0.9em;cursor:default;padding:0px 5px;">{{Present}}} (' . ucfirst($antennaName) .')</span></br>';
	        } else {
	            $states = $states . '<span class="label" style="font-size : 0.9em;cursor:default;padding:0px 5px;background-color:#cccc00">{{Absent}} (' . ucfirst($antennaName) .')</span></br>';
		}
	}
	$present = 0;
	$presentcmd = $eqLogic->getCmd('info', 'state');
	if (is_object($presentcmd)) {
		$present = $presentcmd->execCmd();
	}
	if ($present == 1){
		$present = '<span class="label label-success" style="font-size : 1em;" title="{{Présent}}"><i class="fas fa-check"></i></span>';
	} else {
		$present = '<span class="label label-danger" style="font-size : 1em;" title="{{Absent}}"><i class="fas fa-times"></i></span>';
	}
	echo '<td>' . $states . '</td>';
	echo '<td>' . $present . '</td>';
	echo '<td><span class="label label-info" style="font-size : 1em;cursor:default;">' . $eqLogic->getStatus('lastCommunication') . '</span></td>';
	echo '<td><span class="label label-info" style="font-size : 1em;cursor:default;">' . $eqLogic->getConfiguration('createtime') . '</span></td></tr>';
}
?>
	</tbody>
</table>
