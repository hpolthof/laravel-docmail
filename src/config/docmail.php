<?php
return [

    'connection' => [
        'application_name' => 'Docmail for Laravel',

        /*
         * For testing you can setup a test account at:
         * https://www.cfhdocmail.com/Test
         */
        //'wsdl' => 'https://www.cfhdocmail.com/TestAPI2/DMWS.asmx?WSDL',
        'wsdl' => 'https://www.cfhdocmail.com/LiveAPI2/DMWS.asmx?WSDL',

        'username' => '',
        'password' => '',

        'timeout' => 240,
    ],

    /**
     * Supply an email address to receive processing feedback.
     * Leave blank to receive none.
     */
    'feedback_email' => '',

    /**
     * To auto approve mailings, set this setting to true.
     */
    'submit_after_send' => false,

    /**
     * Specify as "Invoice" or "Topup". If not supplied defaults to "Invoice"
     * if the account can pay on invoice, otherwise defaults to pay from Topup credit.
     */
    'paymentmethod' => 'Topup',

    'defaults' => [
        'duplex' => true,
        'colour' => false,
        'delivery' => 'Standard',
    ],

];