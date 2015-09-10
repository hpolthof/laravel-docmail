# Laravel Package for Docmail
This packages provides an interface to the mail services of [Docmail](https://www.cfhdocmail.com/). The provide their services in many countries and are able to process mailings at fair rates.

## Installing the package

You can install this package using [Composer](https://www.getcomposer.org/). Go to your commandline and run in the root of your project:

```
composer require hpolthof/laravel-docmail
```

Next, open your ```config/app.php``` file and add the following service provider:

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

## API Reference

For the detailed API Reference please refer to the [API Documentation](http://hpolthof.github.io/laravel-docmail/docs/namespaces/Hpolthof.Docmail.html).

## Important notice

Although you can run the interaction with Docmail, within your controller. It is advised to make use of Jobs that are
processed in the background. Although the processing is mostly done within a few seconds, the processing at the Docmail
server can take up to a few minutes. Therefor background Jobs should be used to maintain optimal performance.

> Read more on creating Jobs in the [Laravel Documentation](http://laravel.com/docs/5.1/queues#writing-job-classes).
