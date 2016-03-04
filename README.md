Nette Neon for Symfony
======================

[![Latest Stable Version](https://poser.pugx.org/symfonette/neon-integration/version)](https://packagist.org/packages/symfonette/neon-integration)
[![Build Status](https://travis-ci.org/symfonette/neon-integration.svg?branch=master)](https://travis-ci.org/symfonette/neon-integration)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/symfonette/neon-integration/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/symfonette/neon-integration/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/1c5b8c0b-b0b8-4dff-a103-1b947294b59d/mini.png)](https://insight.sensiolabs.com/projects/1c5b8c0b-b0b8-4dff-a103-1b947294b59d)

Provides integration for Nette Neon with Symfony Dependency Injection component.

Installation
------------

With composer:
```
composer require symfonette/neon-integration
```

You can use it with Dependency Injection component:
```php
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfonette\NeonIntegration\DependencyInjection\NeonFileLoader;

$container = new ContainerBuilder();
$loader = new NeonFileLoader($container);
$loader->load('/path/to/config.neon');
```

If you are using Symfony framework you can update your ``app/AppKernel.php`` file:
```php
use Symfony\Component\HttpKernel\Kernel;
use Symfonette\NeonIntegration\HttpKernel\NeonContainerLoaderTrait;

class AppKernel extends Kernel
{
    use NeonContainerLoaderTrait;

    // ...
}
```

Usages
------

### Services configuration in NEON

#### Class

```yaml
# simplified syntax
services:
    mailer: Mailer

# standard YAML syntax
services:
    mailer:
        class: Mailer
```

#### Arguments

```yaml
# simplified syntax
services:
    newsletter_manager: NewsletterManager(@my_mailer)
    article__manager:
        class: ArticleManager(@doctrine)

# standard YAML syntax
services:
    newsletter_manager:
         class:     NewsletterManager
         arguments: ['@my_mailer']
```

#### Calls

```yaml
# simplified syntax
calls:
    - 
```

#### Calls and properties

```yaml

```


#### Factory

```yaml
# simplified syntax
services:
  form:
    class: App\Form
    factory: App\FormFactory::createForm('registration')
    
# standard YAML syntax
services:
  form:
    class: App\Form
    factory: App\FormFactory::createForm('registration')
```

#### Autowiring

```yaml
# simplified syntax
services:
    twitter_client: AppBundle\TwitterClient(...)
    facebook_client: AppBundle\FacebookClient(..., %kernel.root_dir%)

# standard YAML syntax
services:
    twitter_client:
        class: "AppBundle\TwitterClient"
        autowire: true
    facebook_client: 
        class: "AppBundle\FacebookClient"
        autowire: true
        arguments: ['', %kernel.root_dir%]
```

#### Alias

```yaml
# simplified syntax
services:
    translator: @translator_default

# standard YAML syntax
services:       
    translator:           
        alias: translator_default
```


#### Parent

```yaml
# simplified syntax
services:
    newsletter_manager < mail_manager: NewsletterManager

# standard YAML syntax
services:
    newsletter_manager:
        class: NewsletterManager
        parent: mail_manager
```

#### Expression

```yaml
# alternative syntax
services:
    my_mailer:
        class: Acme\HelloBundle\Mailer
        arguments:
            - expr("service('mailer_configuration').getMailerMethod()")
            #- expression("service('mailer_configuration').getMailerMethod()")

# standard YAML syntax
services:
    my_mailer:
        class: Acme\HelloBundle\Mailer
        arguments:
            - "@=service('mailer_configuration').getMailerMethod()"
```

#### Tags

```yaml
# symplified syntax
services:
    app.response_listener:
        class: ResponseListener
        tags:
            - kernel.event_listener(event=kernel.response, method=onKernelResponse)
    captcha:
        class: CaptchaType
        tags:
            - form.type
            
# standard YAML syntax
services:
    app.response_listener:
        class: ResponseListener
        tags:
            - { name: kernel.event_listener, event: kernel.response, method: onKernelResponse }
    captcha:
        class: CaptchaType
        tags:
            - { name: form.type }
```

### Anonymous services

```yaml
services:
    mailer: Mailer(Zend_Mail_Transport_Smtp('smtp.gmail.com'))
```
