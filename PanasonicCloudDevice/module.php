<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class PanasonicCloudDevice extends IPSModule
{
    use PanasonicCloud\StubsCommonLib;
    use PanasonicCloudLocalLib;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonContruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public static $AIRFLOW_SWING_UD = 0;
    public static $AIRFLOW_SWING_UD_LR = 1;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyBoolean('log_no_parent', true);

        $this->RegisterPropertyString('guid', '');
        $this->RegisterPropertyInteger('type', 0);
        $this->RegisterPropertyString('model', '');

        $this->RegisterPropertyInteger('airflow_swing', self::$AIRFLOW_SWING_UD);
        $this->RegisterPropertyBoolean('with_nanoe', false);
        $this->RegisterPropertyBoolean('with_energy', false);
        $this->RegisterPropertyBoolean('combine_fan_with_nanoe', false);

        $this->RegisterPropertyInteger('update_interval', 60);

        $this->RegisterAttributeString('device_options', '');
        $this->RegisterAttributeString('external_update_interval', '');

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->ConnectParent('{FA9B3ACC-2056-06B5-4DA6-0C7D375A89FB}');

        $this->RegisterTimer('UpdateStatus', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateStatus", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($timeStamp, $senderID, $message, $data)
    {
        parent::MessageSink($timeStamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        if ($this->version2num($oldInfo) < $this->version2num('1.6')) {
            $r[] = $this->Translate('Delete unused variable \'AirflowDirection\'');
            $r[] = $this->Translate('Delete unused variableprofiles \'PanasonicCloud.AirflowDirection_0\', \'PanasonicCloud.AirflowDirection_1\'');
        }

        if ($this->version2num($oldInfo) < $this->version2num('1.6.1')) {
            $r[] = $this->Translate('Delete unused variable \'AirflowAutoMode\'');
            $r[] = $this->Translate('Delete unused variableprofile \'PanasonicCloud.AirflowAutoMode_0\', \'PanasonicCloud.AirflowAutoMode_1\'');
            $r[] = $this->Translate('Adjust variableprofiles \'PanasonicCloud.AirflowVertical\', \'PanasonicCloud.AirflowHorizontal\'');
        }

        if ($this->version2num($oldInfo) < $this->version2num('1.7.1')) {
            $r[] = $this->Translate('Delete unused variable \'LastChange\'');
            $r[] = $this->Translate('Adjust variableprofiles \'PanasonicCloud.Temperature\', \'PanasonicCloud.EcoMode\'');
        }

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        if ($this->version2num($oldInfo) < $this->version2num('1.6')) {
            $this->UnregisterVariable('AirflowDirection');
            if (IPS_VariableProfileExists('PanasonicCloud.AirflowDirection_0')) {
                IPS_DeleteVariableProfile('PanasonicCloud.AirflowDirection_0');
            }
            if (IPS_VariableProfileExists('PanasonicCloud.AirflowDirection_1')) {
                IPS_DeleteVariableProfile('PanasonicCloud.AirflowDirection_1');
            }
        }

        if ($this->version2num($oldInfo) < $this->version2num('1.6.1')) {
            $this->UnregisterVariable('AirflowAutoMode');
            if (IPS_VariableProfileExists('PanasonicCloud.AirflowAutoMode_0')) {
                IPS_DeleteVariableProfile('PanasonicCloud.AirflowAutoMode_0');
            }
            if (IPS_VariableProfileExists('PanasonicCloud.AirflowAutoMode_1')) {
                IPS_DeleteVariableProfile('PanasonicCloud.AirflowAutoMode_1');
            }
            if (IPS_VariableProfileExists('PanasonicCloud.AirflowVertical')) {
                IPS_DeleteVariableProfile('PanasonicCloud.AirflowVertical');
            }
            if (IPS_VariableProfileExists('PanasonicCloud.AirflowHorizontal')) {
                IPS_DeleteVariableProfile('PanasonicCloud.AirflowHorizontal');
            }
            $this->InstallVarProfiles(false);
        }

        if ($this->version2num($oldInfo) < $this->version2num('1.7.1')) {
            $this->UnregisterVariable('LastChange');
            if (IPS_VariableProfileExists('PanasonicCloud.Temperature')) {
                IPS_DeleteVariableProfile('PanasonicCloud.Temperature');
            }
            if (IPS_VariableProfileExists('PanasonicCloud.EcoMode')) {
                IPS_DeleteVariableProfile('PanasonicCloud.EcoMode');
            }
            $this->InstallVarProfiles(false);
        }

        return '';
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 0;

        $this->MaintainVariable('Operate', $this->Translate('Operate'), VARIABLETYPE_BOOLEAN, 'PanasonicCloud.Operate', $vpos++, true);
        $this->MaintainAction('Operate', true);

        $this->MaintainVariable('OperationMode', $this->Translate('Operation mode'), VARIABLETYPE_INTEGER, 'PanasonicCloud.OperationMode', $vpos++, true);

        $this->MaintainVariable('EcoMode', $this->Translate('Eco mode'), VARIABLETYPE_INTEGER, 'PanasonicCloud.EcoMode', $vpos++, true);
        $this->MaintainVariable('TargetTemperature', $this->Translate('Target temperature'), VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, true);
        $this->MaintainVariable('ActualTemperature', $this->Translate('Actual temperature'), VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, true);
        $this->MaintainVariable('OutsideTemperature', $this->Translate('Outside temperature'), VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, true);

        $this->MaintainVariable('FanSpeed', $this->Translate('Fan speed'), VARIABLETYPE_INTEGER, 'PanasonicCloud.FanSpeed', $vpos++, true);

        $airflow_swing = $this->ReadPropertyInteger('airflow_swing');
        switch ($airflow_swing) {
            case self::$AIRFLOW_SWING_UD:
                $with_vertical = true;
                $with_horizontal = false;
                break;
            case self::$AIRFLOW_SWING_UD_LR:
                $with_vertical = true;
                $with_horizontal = true;
                break;
            default:
                $with_vertical = true;
                $with_horizontal = true;
                break;
        }

        $this->MaintainVariable('AirflowVertical', $this->Translate('Vertical direction'), VARIABLETYPE_INTEGER, 'PanasonicCloud.AirflowVertical', $vpos++, $with_vertical);
        $this->MaintainVariable('AirflowHorizontal', $this->Translate('Horizontal direction'), VARIABLETYPE_INTEGER, 'PanasonicCloud.AirflowHorizontal', $vpos++, $with_horizontal);

        $with_nanoe = $this->ReadPropertyBoolean('with_nanoe');
        $this->MaintainVariable('NanoeMode', $this->Translate('nanoe X-function'), VARIABLETYPE_INTEGER, 'PanasonicCloud.NanoeMode', $vpos++, $with_nanoe);

        $vpos = 20;
        $with_energy = $this->ReadPropertyBoolean('with_energy');
        $this->MaintainVariable('DailyEnergyConsumption', $this->Translate('Daily energy consumption'), VARIABLETYPE_FLOAT, 'PanasonicCloud.Energy', $vpos++, $with_energy);
        if ($with_energy) {
            $this->SetVariableLogging('DailyEnergyConsumption', 1 /* Zähler */);
        }

        $vpos = 90;
        $this->MaintainVariable('LastSync', $this->Translate('Last synchronisation'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $s = '';
        $guid = $this->ReadPropertyString('guid');
        if ($guid != '') {
            $r = explode('+', $guid);
            if (is_array($r) && count($r) == 2) {
                $s = $r[0] . '(#' . $r[1] . ')';
            }
        }
        $this->SetSummary($s);

        $this->MaintainStatus(IS_ACTIVE);

        $this->AdjustActions();

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Panasonic ComfortCloud Device');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Basic configuration',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'guid',
                    'caption' => 'Device-ID',
                    'enabled' => false
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'model',
                    'caption' => 'Model',
                    'enabled' => false
                ],
                [
                    'type'    => 'Select',
                    'options' => $this->DeviceTypeAsOptions(),
                    'caption' => 'Type',
                    'enabled' => false
                ],
            ],
        ];

        $formElements[] = [
            'type'     => 'Select',
            'options'  => [
                [
                    'caption' => $this->Translate('vertical only'),
                    'value'   => self::$AIRFLOW_SWING_UD,
                ],
                [
                    'caption' => $this->Translate('vertical and horizontal'),
                    'value'   => self::$AIRFLOW_SWING_UD_LR,
                ],
            ],
            'name'     => 'airflow_swing',
            'caption'  => 'Airflow direction swing',
        ];

        $formElements[] = [
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_nanoe',
                    'caption' => 'has nanoe™ X technology',
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'combine_fan_with_nanoe',
                    'caption' => 'combine mode "fan" with nanoe',
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_energy',
                    'caption' => 'save daily energy consumption'
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' ... by activating this switch, additional variables are created and logged as counters',
                    'italic'  => true,
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'suffix'  => 'Seconds',
            'minimum' => 0,
            'caption' => 'Update interval',
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'log_no_parent',
            'caption' => 'Generate message when the gateway is inactive',
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update status',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateStatus", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded'  => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ],
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function SetUpdateInterval(int $sec = null)
    {
        if (is_null($sec)) {
            $sec = $this->ReadAttributeString('external_update_interval');
            if ($sec == '') {
                $sec = $this->ReadPropertyInteger('update_interval');
            }
        }
        $this->MaintainTimer('UpdateStatus', $sec * 1000);
    }

    public function OverwriteUpdateInterval(int $sec = null)
    {
        if (is_null($sec)) {
            $this->WriteAttributeString('external_update_interval', '');
        } else {
            $this->WriteAttributeString('external_update_interval', $sec);
        }
        $this->SetUpdateInterval($sec);
    }

    private function UpdateStatus()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent/gateway', 0);
            $log_no_parent = $this->ReadPropertyBoolean('log_no_parent');
            if ($log_no_parent) {
                $this->LogMessage($this->Translate('Instance has no active gateway'), KL_WARNING);
            }
            return;
        }

        $guid = $this->ReadPropertyString('guid');

        $airflow_swing = $this->ReadPropertyInteger('airflow_swing');
        switch ($airflow_swing) {
            case self::$AIRFLOW_SWING_UD:
                $with_vertical = true;
                $with_horizontal = false;
                break;
            case self::$AIRFLOW_SWING_UD_LR:
                $with_vertical = true;
                $with_horizontal = true;
                break;
            default:
                $with_vertical = true;
                $with_horizontal = true;
                break;
        }

        $sdata = [
            'DataID'   => '{34871A78-6B14-6BD4-3BE2-192BCB0B150D}',
            'CallerID' => $this->InstanceID,
            'Function' => 'GetDeviceStatusNow',
            'Guid'     => $guid,
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

        $now = time();

        $optNames = [
            'autoMode',
            'coolMode',
            'dryMode',
            'fanMode',
            'heatMode',
            'powerfulMode',
            'quietMode',

            'nanoe',

            'airSwingLR',
            'autoSwingUD',
            'fanDirectionMode',
            'fanSpeedMode',
            'nanoeStandAlone',
        ];
        $options = [];
        foreach ($optNames as $name) {
            $options[$name] = isset($jdata[$name]) ? $jdata[$name] : '';
        }

        if ($options['nanoeStandAlone'] == 1) {
            $options['fanMode'] = 1;
        }

        $s = json_encode($options);
        $this->SendDebug(__FUNCTION__, 'options=' . print_r($options, true), 0);
        if ($this->ReadAttributeString('device_options') != $s) {
            $this->WriteAttributeString('device_options', $s);
        }

        $is_changed = false;
        $fnd = false;

        $used_fields = [];
        $missing_fields = [];
        $ignored_fields = [];

        $operate = $this->GetArrayElem($jdata, 'parameters.operate', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'parameters.operate';
            $this->SendDebug(__FUNCTION__, '... Operate (operate)=' . $operate, 0);
            $this->SaveValue('Operate', (bool) $operate, $is_changed);
        }

        $operationMode = $this->GetArrayElem($jdata, 'parameters.operationMode', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'parameters.operationMode';
            $this->SendDebug(__FUNCTION__, '... OperationMode (operationMode)=' . $operationMode, 0);
            $this->SaveValue('OperationMode', (int) $operationMode, $is_changed);
        }

        $ecoMode = $this->GetArrayElem($jdata, 'parameters.ecoMode', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'parameters.ecoMode';
            $this->SendDebug(__FUNCTION__, '... EcoMode (ecoMode)=' . $ecoMode, 0);
            $this->SaveValue('EcoMode', (int) $ecoMode, $is_changed);
        }

        $fanAutoMode = (string) $this->GetArrayElem($jdata, 'parameters.fanAutoMode', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'parameters.fanAutoMode';
            $this->SendDebug(__FUNCTION__, '... fanAutoMode=' . $fanAutoMode, 0);
        }

        $fanSpeed = $this->GetArrayElem($jdata, 'parameters.fanSpeed', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'parameters.fanSpeed';
            $this->SendDebug(__FUNCTION__, '... FanSpeed (fanSpeed)=' . $fanSpeed, 0);
            $this->SaveValue('FanSpeed', (int) $fanSpeed, $is_changed);
        }

        $airSwingUD = $this->GetArrayElem($jdata, 'parameters.airSwingUD', '', $fnd);
        if ($with_vertical && $fnd) {
            $used_fields[] = 'parameters.airSwingUD';
            if (in_array($fanAutoMode, [self::$AIRFLOW_AUTOMODE_ON, self::$AIRFLOW_AUTOMODE_VERTICAL])) {
                $airSwingUD = self::$AIRFLOW_VERTICAL_AUTO;
            }
            $this->SendDebug(__FUNCTION__, '... AirflowVertical (airSwingUD)=' . $airSwingUD, 0);
            $this->SaveValue('AirflowVertical', (int) $airSwingUD, $is_changed);
        }

        $airSwingLR = $this->GetArrayElem($jdata, 'parameters.airSwingLR', '', $fnd);
        if ($with_horizontal && $fnd) {
            $used_fields[] = 'parameters.airSwingLR';
            if (in_array($fanAutoMode, [self::$AIRFLOW_AUTOMODE_ON, self::$AIRFLOW_AUTOMODE_HORIZONTAL])) {
                $airSwingLR = self::$AIRFLOW_HORIZONTAL_AUTO;
            }
            $this->SendDebug(__FUNCTION__, '... AirflowHorizontal (airSwingLR)=' . $airSwingLR, 0);
            $this->SaveValue('AirflowHorizontal', (int) $airSwingLR, $is_changed);
        }

        $temperatureSet = $this->GetArrayElem($jdata, 'parameters.temperatureSet', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'parameters.temperatureSet';
            $this->SendDebug(__FUNCTION__, '... TargetTemperature (temperatureSet)=' . $temperatureSet, 0);
            $this->SaveValue('TargetTemperature', (float) $temperatureSet, $is_changed);
        }

        $insideTemperature = $this->GetArrayElem($jdata, 'parameters.insideTemperature', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'parameters.insideTemperature';
            $this->SendDebug(__FUNCTION__, '... ActualTemperature (insideTemperature)=' . $insideTemperature, 0);
            $this->SaveValue('ActualTemperature', (float) $insideTemperature, $is_changed);
        }

        $outTemperature = $this->GetArrayElem($jdata, 'parameters.outTemperature', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'parameters.outTemperature';
            $this->SendDebug(__FUNCTION__, '... OutsideTemperature (outTemperature)=' . $outTemperature, 0);
            $this->SaveValue('OutsideTemperature', (float) $outTemperature, $is_changed);
        }

        $with_nanoe = $this->ReadPropertyBoolean('with_nanoe');
        if ($with_nanoe) {
            $nanoe = $this->GetArrayElem($jdata, 'parameters.nanoe', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'parameters.nanoe';
                $this->SendDebug(__FUNCTION__, '... NanoeMode (nanoe)=' . $nanoe, 0);
                $this->SaveValue('NanoeMode', (int) $nanoe, $is_changed);
            }
        }

        if (isset($jdata['timestamp'])) {
            $this->SetValue('LastSync', floor(intval($jdata['timestamp']) / 1000));
        }

        $with_energy = $this->ReadPropertyBoolean('with_energy');
        if ($with_energy) {
            $sdata = [
                'DataID'    => '{34871A78-6B14-6BD4-3BE2-192BCB0B150D}',
                'CallerID'  => $this->InstanceID,
                'Function'  => 'GetDeviceHistory',
                'Guid'      => $guid,
                'DataMode'  => self::$DATA_MODE_DAY,
                'Timestamp' => time(),
            ];
            $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
            $data = $this->SendDataToParent(json_encode($sdata));
            $jdata = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
            $energyConsumption = $this->GetArrayElem($jdata, 'energyConsumption', 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, '... energyConsumption=' . $energyConsumption, 0);
                $this->SaveValue('DailyEnergyConsumption', (float) $energyConsumption, $is_changed);
            }
        }

        $this->SetValue('LastUpdate', $now);

        $this->AdjustActions();

        $this->SetUpdateInterval();
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'UpdateStatus':
                $this->UpdateStatus();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', value=' . $value, 0);

        $r = false;
        $interval = 3000;
        switch ($ident) {
            case 'Operate':
                $r = $this->SetOperate((bool) $value);
                $interval = 4000;
                break;
            case 'OperationMode':
                $r = $this->SetOperateMode((int) $value);
                break;
            case 'EcoMode':
                $r = $this->SetEcoMode((int) $value);
                break;
            case 'TargetTemperature':
                $r = $this->SetTargetTemperature((float) $value);
                break;
            case 'FanSpeed':
                $r = $this->SetFanSpeed((int) $value);
                break;
            case 'AirflowVertical':
                $r = $this->SetAirflowVertical((int) $value);
                break;
            case 'AirflowHorizontal':
                $r = $this->SetAirflowHorizontal((int) $value);
                break;
            case 'NanoeMode':
                $r = $this->SetNanoeMode((int) $value);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
            $this->MaintainTimer('UpdateStatus', $interval);
        }
    }

    private function CheckAction($func, $verbose)
    {
        return true;
    }

    private function AdjustActions()
    {
        $chg = false;

        $operate = $this->GetValue('Operate');
        $ecoMode = $this->GetValue('EcoMode');

        $chg |= $this->AdjustAction('OperationMode', $operate);

        $chg |= $this->AdjustAction('EcoMode', $operate);

        $b = $operate && $ecoMode == self::$ECO_MODE_AUTO;
        $chg |= $this->AdjustAction('FanSpeed', $b);

        $airflow_swing = $this->ReadPropertyInteger('airflow_swing');
        switch ($airflow_swing) {
            case self::$AIRFLOW_SWING_UD:
                $with_vertical = true;
                $with_horizontal = false;
                break;
            case self::$AIRFLOW_SWING_UD_LR:
                $with_vertical = true;
                $with_horizontal = true;
                break;
            default:
                $with_vertical = true;
                $with_horizontal = true;
                break;
        }

        if ($with_vertical) {
            $chg |= $this->AdjustAction('AirflowVertical', $operate);
        }
        if ($with_horizontal) {
            $chg |= $this->AdjustAction('AirflowHorizontal', $operate);
        }

        $chg |= $this->AdjustAction('TargetTemperature', $operate);

        $with_nanoe = $this->ReadPropertyBoolean('with_nanoe');
        if ($with_nanoe) {
            $chg |= $this->AdjustAction('NanoeMode', $operate);
        }

        if ($chg) {
            $this->ReloadForm();
        }
    }

    public function SetOperate(bool $state)
    {
        if ($this->CheckAction(__FUNCTION__, true) == false) {
            return false;
        }

        $parameters = [
            'operate' => $state ? 1 : 0,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetOperateMode(int $value)
    {
        if ($this->CheckAction(__FUNCTION__, true) == false) {
            return false;
        }

        $options = json_decode($this->ReadAttributeString('device_options'), true);
        if (is_array($options)) {
            $map = [
                self::$OPERATION_MODE_AUTO => 'autoMode',
                self::$OPERATION_MODE_DRY  => 'dryMode',
                self::$OPERATION_MODE_COOL => 'coolMode',
                self::$OPERATION_MODE_FAN  => 'fanMode',
                self::$OPERATION_MODE_HEAT => 'heatMode',
            ];
            if (isset($map[$value]) && $options[$map[$value]] != 1) {
                $s = $this->CheckVarProfile4Value('PanasonicCloud.OperationMode', $value);
                $this->SendDebug(__FUNCTION__, 'mode ' . $value . '(' . $s . ') is not allowed on this device/in this context', 0);
                return false;
            }
        }

        $parameters = [
            'operationMode' => $value,
        ];

        $combine_fan_with_nanoe = $this->ReadPropertyBoolean('combine_fan_with_nanoe');
        if ($combine_fan_with_nanoe) {
            if ($value == self::$OPERATION_MODE_FAN && $options['nanoeStandAlone'] == 1) {
                $this->SendDebug(__FUNCTION__, 'add nanoeStandAlone mode', 0);

                $parameters['nanoe'] = self::$NANOE_MODE_ON;
            }
        }

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetEcoMode(int $value)
    {
        if ($this->CheckAction(__FUNCTION__, true) == false) {
            return false;
        }

        $options = json_decode($this->ReadAttributeString('device_options'), true);
        if (is_array($options)) {
            $map = [
                self::$ECO_MODE_POWERFUL => 'powerfulMode',
                self::$ECO_MODE_QUIET    => 'quietMode',
            ];
            if (isset($map[$value]) && $options[$map[$value]] != 1) {
                $s = $this->CheckVarProfile4Value('PanasonicCloud.EcoMode', $value);
                $this->SendDebug(__FUNCTION__, 'mode ' . $value . '(' . $s . ') is not allowed on this device/in this context', 0);
                return false;
            }
        }

        $parameters = [
            'ecoMode' => $value,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetTargetTemperature(float $value)
    {
        if ($this->CheckAction(__FUNCTION__, true) == false) {
            return false;
        }

        $parameters = [
            'temperatureSet' => $value,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetFanSpeed(int $value)
    {
        if ($this->CheckAction(__FUNCTION__, true) == false) {
            return false;
        }

        $parameters = [
            'fanSpeed' => $value,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetAirflowVertical(int $value)
    {
        if ($this->CheckAction(__FUNCTION__, true) == false) {
            return false;
        }

        $airflow_swing = $this->ReadPropertyInteger('airflow_swing');
        switch ($airflow_swing) {
            case self::$AIRFLOW_SWING_UD:
                $with_vertical = true;
                $with_horizontal = false;
                break;
            case self::$AIRFLOW_SWING_UD_LR:
                $with_vertical = true;
                $with_horizontal = true;
                break;
            default:
                $with_vertical = true;
                $with_horizontal = true;
                break;
        }

        $autoLR = $with_horizontal ? $this->GetValue('AirflowHorizontal') == self::$AIRFLOW_HORIZONTAL_AUTO : false;
        if ($value == self::$AIRFLOW_VERTICAL_AUTO) {
            $value = self::$AIRFLOW_VERTICAL_MID;
            $fanAutoMode = $autoLR ? self::$AIRFLOW_AUTOMODE_ON : self::$AIRFLOW_AUTOMODE_VERTICAL;
        } else {
            $fanAutoMode = $autoLR ? self::$AIRFLOW_AUTOMODE_HORIZONTAL : self::$AIRFLOW_AUTOMODE_OFF;
        }

        $parameters = [
            'fanAutoMode' => $fanAutoMode,
            'airSwingUD'  => $value,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetAirflowHorizontal(int $value)
    {
        if ($this->CheckAction(__FUNCTION__, true) == false) {
            return false;
        }

        $airflow_swing = $this->ReadPropertyInteger('airflow_swing');
        switch ($airflow_swing) {
            case self::$AIRFLOW_SWING_UD:
                $with_vertical = true;
                $with_horizontal = false;
                break;
            case self::$AIRFLOW_SWING_UD_LR:
                $with_vertical = true;
                $with_horizontal = true;
                break;
            default:
                $with_vertical = true;
                $with_horizontal = true;
                break;
        }

        $autoUD = $with_vertical ? $this->GetValue('AirflowVertical') == self::$AIRFLOW_VERTICAL_AUTO : false;
        if ($value == self::$AIRFLOW_HORIZONTAL_AUTO) {
            $value = self::$AIRFLOW_HORIZONTAL_MID;
            $fanAutoMode = $autoUD ? self::$AIRFLOW_AUTOMODE_ON : self::$AIRFLOW_AUTOMODE_HORIZONTAL;
        } else {
            $fanAutoMode = $autoUD ? self::$AIRFLOW_AUTOMODE_VERTICAL : self::$AIRFLOW_AUTOMODE_OFF;
        }

        $parameters = [
            'fanAutoMode' => $fanAutoMode,
            'airSwingLR'  => $value,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetNanoeMode(int $value)
    {
        if ($this->CheckAction(__FUNCTION__, true) == false) {
            return false;
        }

        $options = json_decode($this->ReadAttributeString('device_options'), true);
        if (isset($map['nanoe']) && $options[$map['nanoe']] != 1) {
            $this->SendDebug(__FUNCTION__, 'nanoe X-technology is not avail on this device', 0);
            return false;
        }

        $combine_fan_with_nanoe = $this->ReadPropertyBoolean('combine_fan_with_nanoe');
        if ($combine_fan_with_nanoe) {
            $mode = $this->GetValue('OperationMode');
            if ($mode == self::$OPERATION_MODE_FAN && $value == self::$NANOE_MODE_OFF) {
                $this->SendDebug(__FUNCTION__, 'fan mode requires active nanoe X', 0);
                return false;
            }
        }

        $parameters = [
            'nanoe' => $value,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    private function ControlDevice($func, array $parameters)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent/gateway', 0);
            $log_no_parent = $this->ReadPropertyBoolean('log_no_parent');
            if ($log_no_parent) {
                $this->LogMessage($this->Translate('Instance has no active gateway'), KL_WARNING);
            }
            return;
        }

        $guid = $this->ReadPropertyString('guid');

        $sdata = [
            'DataID'     => '{34871A78-6B14-6BD4-3BE2-192BCB0B150D}',
            'CallerID'   => $this->InstanceID,
            'Function'   => 'ControlDevice',
            'Guid'       => $guid,
            'Parameters' => json_encode($parameters),
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

        return isset($jdata['result']) && $jdata['result'] == 0;
    }
}
