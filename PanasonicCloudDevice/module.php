<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class PanasonicCloudDevice extends IPSModule
{
    use PanasonicCloud\StubsCommonLib;
    use PanasonicCloudLocalLib;

    private $ModuleDir;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('guid', '');
        $this->RegisterPropertyInteger('type', 0);
        $this->RegisterPropertyString('model', '');

        $this->RegisterPropertyInteger('update_interval', 60);

        $this->RegisterAttributeString('device_options', '');
        $this->RegisterAttributeString('external_update_interval', '');

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('UpdateStatus', 0, $this->GetModulePrefix() . '_UpdateStatus(' . $this->InstanceID . ');');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        $this->ConnectParent('{FA9B3ACC-2056-06B5-4DA6-0C7D375A89FB}');
    }

    public function MessageSink($timeStamp, $senderID, $message, $data)
    {
        parent::MessageSink($timeStamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $this->OverwriteUpdateInterval();
        }
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->SetStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 0;

        $this->MaintainVariable('Operate', $this->Translate('Operate'), VARIABLETYPE_BOOLEAN, 'PanasonicCloud.Operate', $vpos++, true);
        $this->MaintainAction('Operate', true);

        $this->MaintainVariable('OperationMode', $this->Translate('Operation mode'), VARIABLETYPE_INTEGER, 'PanasonicCloud.OperationMode', $vpos++, true);

        $this->MaintainVariable('EcoMode', $this->Translate('Eco mode'), VARIABLETYPE_INTEGER, 'PanasonicCloud.EcoMode', $vpos++, true);
        $this->MaintainVariable('TargetTemperature', $this->Translate('Target temperature'), VARIABLETYPE_FLOAT, '', $vpos++, true);
        $this->MaintainVariable('ActualTemperature', $this->Translate('Actual temperature'), VARIABLETYPE_FLOAT, '', $vpos++, true);
        $this->MaintainVariable('OutsideTemperature', $this->Translate('Outside temperature'), VARIABLETYPE_FLOAT, '', $vpos++, true);

        $this->MaintainVariable('FanMode', $this->Translate('Fan mode'), VARIABLETYPE_INTEGER, 'PanasonicCloud.FanMode', $vpos++, true);
        $this->MaintainVariable('FanSpeed', $this->Translate('Fan speed'), VARIABLETYPE_INTEGER, 'PanasonicCloud.FanSpeed', $vpos++, true);
        $this->MaintainVariable('AirSwingVertical', $this->Translate('Vertical air swing'), VARIABLETYPE_INTEGER, 'PanasonicCloud.AirSwingVertical', $vpos++, true);
        $this->MaintainVariable('AirSwingHorizontal', $this->Translate('Horizontal air swing'), VARIABLETYPE_INTEGER, 'PanasonicCloud.AirSwingHorizontal', $vpos++, true);

        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('LastChange', $this->Translate('Last change'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->SetStatus(IS_INACTIVE);
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

        $this->SetStatus(IS_ACTIVE);

        $this->AdjustActions();

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->OverwriteUpdateInterval();
        }
    }

    protected function GetFormElements()
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
            'caption' => 'Basic configuration (don\'t change)',
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
                    'type'     => 'Select',
                    'options'  => $this->DeviceTypeAsOptions(),
                    'caption'  => 'Type',
                    'enabled'  => false
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

        return $formElements;
    }

    protected function GetFormActions()
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
            'onClick' => $this->GetModulePrefix() . '_UpdateStatus($id);'
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'Button',
                    'caption' => 'Re-install variable-profiles',
                    'onClick' => $this->GetModulePrefix() . '_InstallVarProfiles($id, true);'
                ],
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded ' => false,
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

    public function UpdateStatus()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return;
        }

        $guid = $this->ReadPropertyString('guid');

        $sdata = [
            'DataID'   => '{34871A78-6B14-6BD4-3BE2-192BCB0B150D}',
            'Function' => 'GetDeviceStatusNow',
            'Guid'     => $guid,
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

        $this->SendDebug(__FUNCTION__, $this->PrintTimer('UpdateStatus'), 0);
        /*

07.05.2022, 18:40:01 |         UpdateStatus | jdata=Array
(
    [airSwingLR] => 1
    [autoMode] => 1
    [autoSwingUD] =>
    [autoTempMax] => -1
    [autoTempMin] => -1
    [coolMode] => 1
    [coolTempMax] => -1
    [coolTempMin] => -1
    [dryMode] => 1
    [dryTempMax] => -1
    [dryTempMin] => -1
    [ecoFunction] => 0
    [ecoNavi] =>
    [fanDirectionMode] => -1
    [fanMode] =>
    [fanSpeedMode] => -1
    [heatMode] => 1
    [heatTempMax] => -1
    [heatTempMin] => -1
    [iAutoX] =>
    [modeAvlList] => Array
        (
            [autoMode] => 1
            [fanMode] => 1
        )
    [nanoe] =>
    [nanoeList] => Array
        (
            [visualizationShow] => 0
        )
    [nanoeStandAlone] =>
    [pairedFlg] =>
    [permission] => 3
    [powerfulMode] => 1
    [quietMode] => 1
    [summerHouse] => 0
    [temperatureUnit] => 0
    [timestamp] => 1651941486000
    [parameters] => Array
        (
            [ecoFunctionData] => 0
            [airSwingLR] => 2
            [nanoe] => 0
            [lastSettingMode] => 0
            [ecoNavi] => 0
            [ecoMode] => 2
            [operationMode] => 2
            [fanAutoMode] => 0
            [errorStatus] => -255
            [temperatureSet] => 23
            [fanSpeed] => 0
            [iAuto] => 0
            [airQuality] => 0
            [insideTemperature] => 22
            [outTemperature] => 17
            [operate] => 1
            [airDirection] => 1
            [actualNanoe] => 0
            [airSwingUD] => 2
        )

)




    if (device.nanoe && device.parameters.nanoe !== undefined) parameters.nanoe = device.parameters.nanoe;
    if (device.nanoeStandAlone && device.parameters.actualNanoe !== undefined) parameters.actualNanoe = device.parameters.actualNanoe;
    $this->MaintainVariable('NanoeMode', $this->Translate('Nanoe  mode', VARIABLETYPE_INTEGER, 'PanasonicCloud.NanoeMode', $vpos++, false);


        if (device.parameters.operate !== undefined) parameters.operate = device.parameters.operate;
        if (device.parameters.fanAutoMode !== undefined) parameters.fanAutoMode = device.parameters.fanAutoMode;
        if (device.parameters.airDirection !== undefined) parameters.airDirection = device.parameters.airDirection;
        if (device.parameters.airSwingLR !== undefined) parameters.airSwingLR = device.parameters.airSwingLR;
        if (device.parameters.airSwingUD !== undefined) parameters.airSwingUD = device.parameters.airSwingUD;
        if (device.parameters.fanSpeed !== undefined) parameters.fanSpeed = device.parameters.fanSpeed;

        if (device.parameters.ecoFunctionData !== undefined) parameters.ecoFunctionData = device.parameters.ecoFunctionData;
        if (device.parameters.ecoMode !== undefined) parameters.ecoMode = device.parameters.ecoMode;



        if (
            (device.autoMode && device.parameters.operationMode === OperationMode.Auto) ||
            (device.coolMode && device.parameters.operationMode === OperationMode.Cool) ||
            (device.dryMode && device.parameters.operationMode === OperationMode.Dry) ||
            (device.heatMode && device.parameters.operationMode === OperationMode.Heat) ||
            (device.fanMode && device.parameters.operationMode === OperationMode.Fan)
        )
            parameters.operationMode = device.parameters.operationMode;
         */

        $now = time();
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
            $this->SendDebug(__FUNCTION__, '... FanMode (fanAutoMode)=' . $fanAutoMode, 0);
            $this->SaveValue('FanMode', (int) $fanAutoMode, $is_changed);
        }

        $fanSpeed = $this->GetArrayElem($jdata, 'parameters.fanSpeed', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'parameters.fanSpeed';
            $this->SendDebug(__FUNCTION__, '... FanSpeed (fanSpeed)=' . $fanSpeed, 0);
            $this->SaveValue('FanSpeed', (int) $fanSpeed, $is_changed);
        }

        $airSwingUD = $this->GetArrayElem($jdata, 'parameters.airSwingUD', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'parameters.airSwingUD';
            $this->SendDebug(__FUNCTION__, '... AirSwingVertical (airSwingUD)=' . $airSwingUD, 0);
            $this->SaveValue('AirSwingVertical', (int) $airSwingUD, $is_changed);
        }

        $airSwingLR = $this->GetArrayElem($jdata, 'parameters.airSwingLR', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'parameters.airSwingLR';
            $this->SendDebug(__FUNCTION__, '... AirSwingHorizontal (airSwingLR)=' . $airSwingLR, 0);
            $this->SaveValue('AirSwingHorizontal', (int) $airSwingLR, $is_changed);
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

        $this->SetValue('LastUpdate', $now);
        if ($is_changed) {
            $this->SetValue('LastChange', (int) $jdata['timestamp']);
        }

        $optNames = [
            'autoMode',
            'coolMode',
            'dryMode',
            'fanMode',
            'heatMode',
            'powerfulMode',
            'quietMode',

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
        $s = json_encode($options);
        $this->SendDebug(__FUNCTION__, 'options=' . print_r($options, true), 0);
        if ($this->ReadAttributeString('device_options') != $s) {
            $this->WriteAttributeString('device_options', $s);
        }

        $this->AdjustActions();

        $this->SetUpdateInterval();
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', value=' . $value, 0);

        $r = false;
        switch ($ident) {
            case 'Operate':
                $r = $this->SetOperate((bool) $value);
                break;
            case 'OperationMode':
                $r = $this->SetOperateMode((int) $value);
                break;
            case 'EcoMode':
                $r = $this->SetEcoMode((int) $value);
                break;
            case 'TargetTemperature':
                $r = $this->SetTargetTemperature((int) $value);
                break;
            case 'FanMode':
                $r = $this->SetFanMode((int) $value);
                break;
            case 'FanSpeed':
                $r = $this->SetFanSpeed((int) $value);
                break;
            case 'AirSwingVertical':
                $r = $this->SetAirSwingVertical((int) $value);
                break;
            case 'AirSwingHorizontal':
                $r = $this->SetAirSwingHorizontal((int) $value);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
            $this->MaintainTimer('UpdateStatus', 250);
        }
    }

    private function CheckAction($func, $verbose)
    {
        $enabled = false;

        // $this->SendDebug(__FUNCTION__, 'action "' . $func . '" is ' . ($enabled ? 'enabled' : 'disabled'), 0);
        return true;
    }

    private function AdjustActions()
    {
        $chg = false;

        $operate = $this->GetValue('Operate');
        $fanMode = $this->GetValue('FanMode');

        $chg |= $this->AdjustAction('OperationMode', $operate);
        $chg |= $this->AdjustAction('EcoMode', $operate);
        $chg |= $this->AdjustAction('TargetTemperature', $operate);
        $chg |= $this->AdjustAction('FanMode', $operate);
        $chg |= $this->AdjustAction('FanSpeed', $operate);
        $chg |= $this->AdjustAction('AirSwingVertical', $operate);
        $chg |= $this->AdjustAction('AirSwingHorizontal', $operate);

        if ($chg) {
            $this->ReloadForm();
        }
    }

    public function SetOperate(bool $state)
    {
        if (!$this->CheckAction(__FUNCTION__, true)) {
            return false;
        }

        $parameters = [
            'operate' => $state ? 1 : 0,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetOperateMode(int $value)
    {
        if (!$this->CheckAction(__FUNCTION__, true)) {
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

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetEcoMode(int $value)
    {
        if (!$this->CheckAction(__FUNCTION__, true)) {
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

    public function SetTargetTemperature(int $value)
    {
        if (!$this->CheckAction(__FUNCTION__, true)) {
            return false;
        }

        $parameters = [
            'temperatureSet' => $value,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetFanMode(int $value)
    {
        if (!$this->CheckAction(__FUNCTION__, true)) {
            return false;
        }

        $parameters = [
            'fanAutoMode' => $value,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetFanSpeed(int $value)
    {
        if (!$this->CheckAction(__FUNCTION__, true)) {
            return false;
        }

        $parameters = [
            'fanSpeed' => $value,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetAirSwingVertical(int $value)
    {
        if (!$this->CheckAction(__FUNCTION__, true)) {
            return false;
        }

        $parameters = [
            'airSwingUD' => $value,
        ];

        return $this->ControlDevice(__FUNCTION__, $parameters);
    }

    public function SetAirSwingHorizontal(int $value)
    {
        if (!$this->CheckAction(__FUNCTION__, true)) {
            return false;
        }

        $parameters = [
            'airSwingLR' => $value,
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
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return;
        }

        $guid = $this->ReadPropertyString('guid');

        $sdata = [
            'DataID'     => '{34871A78-6B14-6BD4-3BE2-192BCB0B150D}',
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