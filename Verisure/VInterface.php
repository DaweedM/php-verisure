<?php

namespace Verisure;

class VInterface
{
    private $session = null;
    private $httpInterface = null;

    public function __construct(...$args)
    {
        $this->session = new Session(...$args);
        $this->httpInterface = new HTTPInterface();
    }

    public function __destruct()
    {
        if ($this->session->hasCookie()) { // if was logged in
            $this->logout();
        }
    }

    public function login()
    {
        $this->httpInterface->setUrl(SecuritasK::URL_BASE . SecuritasK::URL_LOGIN);
        $this->httpInterface->setSession($this->session);
        $this->httpInterface->setPayload([]);

        $loginResponse = $this->httpInterface->execute();

        $this->session->setCookie($loginResponse->cookie);
    }

    public function logout()
    {
        $this->httpInterface->setUrl(SecuritasK::URL_BASE . SecuritasK::URL_LOGIN);
        $this->httpInterface->setPayload([]);

        $this->httpInterface->execute(HTTPInterface::DELETE);
    }

    public function init($initMode = SecuritasK::INIT_MODE_AUTO)
    {

        $this->httpInterface->setUrl(
            sprintf(SecuritasK::URL_BASE . SecuritasK::GET_INSTALLATIONS_URL, urlencode($this->session->getUser()))
        );

        $installations = $this->httpInterface->execute();
        $this->session->setInstallations($installations);

        if ($initMode === SecuritasK::INIT_MODE_AUTO) {
            // Set automatically first installation as main installation
            $this->session->setInstallation($installations[0]);
        }

    }

    private function isValidSerialNumber($serialNumber)
    {
        return preg_match("/[a-zA-Z0-9]{4} [a-zA-Z0-9]{4}/i", $serialNumber);
    }

    public function setCameraMotionDetectorState($cameraSerial = null, $state = SecuritasK::OFF)
    {
        if ($cameraSerial === null) {
            throw new \Exception("Please provide the serial number of the camera");
        }

        $this->httpInterface->setUrl(
            sprintf(SecuritasK::URL_BASE . SecuritasK::SET_CAMERA_STATE, $this->session->getGiid(), urlencode($cameraSerial))
        );

        $this->httpInterface->setPayload(array(
            "userMonitoredCameraConfiguration" => array(
                "motionDetectorActive" => $state
            ),
            "capability" => "USER_MONITORED_CUSTOMER_IMAGE_CAMERA"
        ));

        return $this->httpInterface->execute(HTTPInterface::PUT);
    }

    public function setSmartPlugState($smartPlugSerial = null, $state = SecuritasK::OFF)
    {
        return $this->setMultipleSmartPlugsState([
            $smartPlugSerial,
            $state
        ]);

    }

    public function setMultipleSmartPlugsState($smartPlugs)
    {

        foreach ($smartPlugs as $smartPlug) {
            $serialNumber = $smartPlug[0];
            $state = $smartPlug[1];
            if (!$this->isValidSerialNumber($serialNumber)) {
                throw new \Exception(sprintf("Wrong serial number format for entry : %s", $serialNumber));
            }
            if (!is_bool($state)) {
                throw new \Exception(sprintf("The state must be a bool value : %s", $state));
            }
        }

        $this->httpInterface->setUrl(
            sprintf(SecuritasK::URL_BASE . SecuritasK::SET_SMARTPLUG_STATE, $this->session->getGiid())
        );

        $this->httpInterface->setPayload(
            array_map(function ($smartPlug) {
                $serialNumber = $smartPlug[0];
                $state = $smartPlug[1];
                return array(
                    "deviceLabel" => $serialNumber,
                    "state" => $state
                );
            }, $smartPlugs)
        );

        return $this->httpInterface->execute(HTTPInterface::POST);
    }
}

?>