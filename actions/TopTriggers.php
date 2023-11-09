<?php
namespace Modules\TopTriggers\Actions;
use CController,
    CControllerResponseData;
class TopTriggers extends CController {
    public function init(): void {
        $this->disableCsrfValidation();
    }
    protected function checkInput(): bool {
        return true;
    }
    protected function checkPermissions(): bool {
        return true;
    }
    protected function doAction(): void {
        $data = ['my-ip'];
        $response = new CControllerResponseData($data);
        $this->setResponse($response);
    }
}