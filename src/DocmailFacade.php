<?php namespace Hpolthof\Docmail;


use Illuminate\Support\Facades\Facade;

class DocmailFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'docmail';
    }

}