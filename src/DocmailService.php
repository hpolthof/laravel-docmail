<?php namespace Hpolthof\Docmail;

use Illuminate\Support\Collection;

/**
 * Class DocmailService
 * @package Hpolthof\Docmail
 * @author Paul Olthof <hpolthof@gmail.com>
 */
class DocmailService
{
    protected $client;
    protected $addresses;
    protected $mailing;
    protected $template;

    protected $MailingGUID;
    protected $OrderRef;

    protected $submitAfterSend = false;
    protected $paymentMethod;


    public function __construct()
    {
        $this->client = new \nusoap_client(config('docmail.connection.wsdl'), true);
        $this->client->timeout = config('docmail.connection.timeout');

        $this->addresses = new Collection();
        $this->setMailing(new DocmailMailing());

        $this->submitAfterSend = config('docmail.submit_after_send', false);
        $this->paymentMethod = config('docmail.paymentmethod', 'Invoice');
    }

    /**
     * Submit the mailing to the Docmail server.
     *
     * @return bool
     * @throws DocmailException
     */
    public function send()
    {
        if(!$this->mailing instanceof DocmailMailing) {
            throw new DocmailException('No mailing was set. You\'ll need to set and mailing before calling send(). Use setMailing().');
        }

        if($this->addresses->count() == 0) {
            throw new DocmailException('No addresses where added. Please add at lease one address before calling send(). Use addAddress().');
        }

        $this->MailingGUID = null;

        // CreateMailing
        $result = $this->callCreateMailing();

        // AddAddress
        if($result) {
            $result = $this->callAddAddress();
        }

        // AddTemplateFile
        if($result) {
            $result = $this->callAddTemplateFile();
        }

        // ProcessMailing
        if($result) {
            $result = $this->callProcessMailing();
        }

        if(!$result && strlen($this->MailingGUID) > 0) {
            // Remove Mailing
            $this->deleteMailing($this->MailingGUID);
        }

        return $result;
    }

    /**
     * Delete a mailing by GUID.
     *
     * @param $MailingGUID
     * @throws DocmailException
     */
    public function deleteMailing($MailingGUID)
    {
        $this->doApiCall('DeleteMailing', $this->mergeRequestParameters(compact('MailingGUID', false)));
    }

    /**
     * Send a file through Docmail with a in a closure configured service object.
     *
     * @param $filename
     * @param \Closure $handler
     * @return DocmailService|null
     * @throws DocmailException
     */
    public function sendFile($filename, \Closure $handler)
    {
        $instance = new \Hpolthof\Docmail\DocmailService();
        $instance->setTemplate(DocmailTemplateFile::loadFromFile($filename));
        $handler($instance);
        
        $result = $instance->send();
        if($result) {
            return $instance;
        }
        return null;
    }

    /**
     * Send a file using a data string through Docmail with a in a closure configured service object.
     *
     * @param $filename
     * @param $data
     * @param \Closure $handler
     * @return DocmailService|null
     * @throws DocmailException
     */
    public function sendData($filename, $data, \Closure $handler)
    {
        $template = new DocmailTemplateFile;
        $template->setFileName($filename);
        $template->setFileData($data);

        $instance = new \Hpolthof\Docmail\DocmailService();
        $instance->setTemplate($template);
        $handler($instance);

        $result = $instance->send();
        if($result) {
            return $instance;
        }
        return null;
    }

    /**
     * Get the Unique GUID Docmail generated for API purposes.
     *
     * @return string|null
     */
    public function getMailingGUID()
    {
        return $this->MailingGUID;
    }

    /**
     * Get the Order Reference Docmail assigned to the mailing.
     *
     * @return string|null
     */
    public function getOrderRef()
    {
        return $this->OrderRef;
    }

    /**
     * Get your current credit or allowed limit, based on your account type.
     *
     * @return float
     * @throws DocmailException
     */
    public function getBalance()
    {
        $response = $this->doApiCall('GetBalance', $this->mergeRequestParameters(['AccountType' => config('docmail.paymentmethod', 'Invoice')], false));
        return floatval($this->getResultField($response, 'Current balance'));
    }

    /**
     * If the order is processed this function will return the content of the Proof PDF File.
     *
     * Keep in mind that after sending a mailing the servers need a few seconds to one minute
     * to process the mailing. Be advised to queue a job for the retrieval of the Proof PDF.
     *
     * @return null|string
     */
    public function getProofFile()
    {
        $response = base64_decode($this->doApiCall('GetProofFile', $this->mergeRequestParameters([])));
        if(substr($response, 0, 5) == 'Error') {
            return null;
        }
        return $response;
    }
    
    /**
     * @return DocmailMailing
     */
    public function getMailing()
    {
        return $this->mailing;
    }

    /**
     * @param mixed $mailing
     * @return $this
     */
    public function setMailing(DocmailMailing $mailing)
    {
        $this->mailing = $mailing;
        return $this;
    }

    /**
     * Add an address using the DocmailAddress.
     *
     * @param DocmailAddress $address
     * @return $this
     */
    public function addAddress(DocmailAddress $address)
    {
        $this->addresses->push($address);
        return $this;
    }

    /**
     * Add an address using basic address information.
     *
     * @param string $fullname
     * @param string $address1
     * @param string $address2
     * @param string $address3
     * @param string $address4
     * @return DocmailService
     */
    public function addBasicAddress($fullname, $address1, $address2, $address3 = '', $address4 = '')
    {
        return $this->addAddress(DocmailAddress::basic($fullname, $address1, $address2, $address3, $address4));
    }

    /**
     * @return DocmailAddress[]
     */
    public function getAddresses()
    {
        return $this->addresses->all();
    }

    private function doApiCall($callName, $params){

        $callResult = $this->client->call($callName, $params);

        if($callResult === false) {
            throw new DocmailException("The SOAP client returned FALSE on call.");
        }

        if(!isset($callResult[$callName."Result"])) {
            throw new DocmailException("The field '{$callName}Result' could not be found in the server response.");
        }

        $this->checkError($callResult[$callName."Result"]);   //parse & check error fields from result as described above
        flush();

        return $callResult[$callName."Result"];
    }

    protected function callCreateMailing()
    {
        if ($this->mailing->getCustomerApplication() == '') {
            $this->mailing->setCustomerApplication(config('docmail.connection.application_name'));
        }

        $params = $this->mergeRequestParameters($this->getMailing()->toArray(), false);

        $result = $this->doApiCall('CreateMailing', $params);
        $this->MailingGUID = $this->getResultField($result, 'MailingGUID');
        $this->OrderRef = $this->getResultField($result, 'OrderRef');

        if (intval($this->OrderRef) > 0) {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     * @throws DocmailException
     */
    protected function callAddAddress()
    {
        foreach ($this->getAddresses() as $address) {
            $params = $this->mergeRequestParameters($address->toArray());
            $response = $this->doApiCall('AddAddress', $params);
            if ($this->getResultField($response, 'Success') == true) {
                continue;
            }
            throw new DocmailException('Address was not successfully added.');
        }
        return true;
    }

    /**
     * @return DocmailTemplateFile
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param DocmailTemplateFile $template
     * @return DocmailService
     */
    public function setTemplate(DocmailTemplateFile $template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return bool
     * @throws DocmailException
     */
    protected function callAddTemplateFile()
    {
        $params = $this->mergeRequestParameters($this->getTemplate()->toArray());
        $response = $this->doApiCall('AddTemplateFile', $params);
        if (strlen($this->getResultField($response, 'TemplateGUID')) > 0) {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     * @throws DocmailException
     */
    protected function callProcessMailing()
    {
        $params = array(
            "CustomerApplication" => $this->mailing->getCustomerApplication(),
            "SkipPreviewImageGeneration" => false,
            "Submit" => config('docmail.submit_after_send', false),
            "PartialProcess" => true,
            "Copies" => 1,
            "ReturnFormat" => "Text",
            "EmailSuccessList" => config('docmail.feedback_email', ''),
            "EmailErrorList" => config('docmail.feedback_email', ''),
            "HttpPostOnSuccess" => '',
            "HttpPostOnError" => ''
        );
        $params = $this->mergeRequestParameters($params);
        $response = $this->doApiCall('ProcessMailing', $params);

        if ($this->getResultField($response, 'Success') == true) {
            return true;
        }
        return false;
    }

    private function doGetStatus($sUsr,$sPwd,$MailingGUID){

        ///////////////////////
        // GetStatus - Setup array to pass into webservice call
        ///////////////////////
        // other available params listed here:  (https://www.cfhdocmail.com/TestAPI2/DMWS.asmx?op=GetStatus) returns the status of a mailing from the mailing guid
        $callResult = "";
        $callName = "GetStatus";
        $params = array(
            "Username" => $sUsr,
            "Password" => $sPwd,
            "MailingGUID" => $MailingGUID,
            "ReturnFormat" => "Text"
        );
        // other available params listed here:  (https://www.cfhdocmail.com/TestAPI2/DMWS.asmx?op=GetStatus) returns the status of a mailing from the mailing guid
        $callResult = $this->doApiCall($callName,$params);

        //$Status = $this->getResultField($callResult, "Status");

        return $callResult;
    }

    private function doWaitForProcessMailingStatus($sUsr,$sPwd,$MailingGUID,$ExpectedStatus,$ExceptionOnFail){

        //poll GetStatus in a loop until the processing has completed
        //loop a maximum of 10 times, with a 10 second delay between iterations.
        //	alternatively; handle callbacks from the HttpPostOnSuccess & HttpPostOnError parameters on ProcessMailing to identify when the processing has completed
        $i = 0;
        do {
            // other available params listed here:  (https://www.cfhdocmail.com/TestAPI2/DMWS.asmx?op=GetStatus) returns the status of a mailing from the mailing guid
            $result = $this->doGetStatus($sUsr,$sPwd,$MailingGUID);

            $Status = $this->getResultField($result,"Status");
            $Error = $this->getResultField($result,"Error code");
            //end loop once processing is complete
            if ($Status== $ExpectedStatus ){break;}	//success
            if ($Status== "Error in processing" ){break;}	//error in processing
            if ($Error ){break;}			//error

            sleep(10);//wait 10 seconds before repeating
            ++$i;
        } while ($i < 10);

        //
        if ($Status == "Error in processing") {
            //get description of error in processing
            $params = array(
                "Username" => $sUsr,
                "Password" => $sPwd,
                "MethodName" => "GetProcessingError",
                "ReturnFormat" => "Text",
                "Properties" => array(
                    "PropertyName" => "GetProcessingError",
                    "PropertyValue" => $MailingGUID
                )
            );
            $result = $this->doApiCall("ExtendedCall" ,$params);
        }

        if ($Status != $ExpectedStatus) {
            if ($ExceptionOnFail){
                throw new DocmailException("<h2>There was an error:</h2> expected status '".$ExpectedStatus."' not reached.  Current status: '".$Status."'<br/>");
            }
        }

        flush();
    }

    private function checkError($Res){
        if ($Res == null) return;

        if (is_array($Res))	reset($Res);
        //check for  the keys 'Error code', 'Error code string' and 'Error message' to test/report errors
        $errCode = $this->getResultField($Res,"Error code");
        if($errCode) {
            $errName = $this->getResultField($Res,"Error code string");
            $errMsg = $this->getResultField($Res,"Error message");
            throw new DocmailException($errCode." ".$errName." - ".$errMsg);
        }
        if (is_array($Res))	reset($Res);
        flush();
    }

    private function getResultField($FldList, $FldName){
        // calls return a multi-line string structured as :
        // [KEY]: [VALUE][carriage return][line feed][KEY]: [VALUE][carriage return][line feed][KEY]: [VALUE][carriage return][line feed][KEY]: [VALUE]
        $lines = explode("\n",$FldList);
        for ( $lineCounter=0;$lineCounter < count($lines); $lineCounter+=1){
            $fields = explode(":",$lines[$lineCounter]);
            //find matching field name
            if ($fields[0]==$FldName)	{
                return ltrim($fields[1], " "); //return value
            }
        }
    }

    private function getRequiredRequestParameters($guid = true)
    {
        $data = [
            'Username' => config('docmail.connection.username'),
            'Password' => config('docmail.connection.password'),
            'ReturnFormat' => 'Text',
        ];
        if($guid) {
            $data['MailingGUID'] = $this->MailingGUID;
        }

        return $data;
    }

    private function mergeRequestParameters($params, $guid = true)
    {
        if($guid == true && strlen($this->MailingGUID) == 0) {
            throw new DocmailException('This call can only be made when a mailing has been send.');
        }

        $result = array_merge($params, $this->getRequiredRequestParameters($guid));
        return array_filter($result, function($v) {
            return $v != '';
        });
    }
}