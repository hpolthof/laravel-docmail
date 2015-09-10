# Laravel Package for Docmail
This packages provides an interface to the mail services of [Docmail](https://www.cfhdocmail.com/). The provide their services in many countries and are able to process mailings at fair rates.

## Installing the package
Open your ```config/app.php``` file and add the following service provider:

```
\Hpolthof\Docmail\DocmailServiceProvider::class,
``` 

Then add the following facade to your list of aliases:

```
'Docmail'   => \Hpolthof\Docmail\DocmailFacade::class,
```

## Usage

```
\Docmail::sendFile(storage_path('temp/test.pdf'), function(\Hpolthof\Docmail\DocmailService $docmail) {
    // Name the mailing, defaults to the OrderRef.
    $docmail->getMailing()->setMailingName('Test Mailing');
    
    // Change the filename.
    $docmail->getTemplate()->setFileName('MyPrettyLetterFilename.pdf');
    
    // Add all the addresses you want.
    $docmail->addBasicAddress('John Doe', 'Testersroad 3', '32444 Testersvalley');

    // If you have a discountcode you can apply it.
    $docmail->getMailing()->setDiscountCode('');
});
```
