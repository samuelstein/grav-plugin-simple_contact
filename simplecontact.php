<?php namespace Grav\Plugin;

use Grav\Common\Plugin;

class SimpleContactPlugin extends Plugin
{
  public static function getSubscribedEvents()
  {
    return [
      'onPluginsInitialized'  => ['onPluginsInitialized', 0],
      'onTwigTemplatePaths'   => ['onTwigTemplatePaths', 0],
      'onTwigSiteVariables'   => ['onTwigSiteVariables', 0],
      'onPageInitialized'     => ['onPageInitialized', 0],
    ];
  }

  public function onPluginsInitialized()
  {
    if ( $this->isAdmin() ) {
      $this->active = false;
      return;
    }
  }

  public function onTwigTemplatePaths()
  {
    if ( ! $this->active ) return;

    $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
  }

  public function onTwigSiteVariables()
  {
    if ( ! $this->active ) return;

    $page = $this->grav['page'];

    if ( isset($page->header()->simplecontact) && (true === $page->header()->simplecontact || is_array($page->header()->simplecontact)) ) {
      $this->grav['assets']
        ->addCss('plugin://simplecontact/assets/css/simplecontact.css')
        ->addJs('plugin://simplecontact/assets/js/simplecontact.js');
    }
  }

  public function onPageInitialized()
  {
    if ( ! $this->active ) return;

    $page = $this->grav['page'];

    if ( isset($page->header()->simplecontact) ) {
      $twig = $this->grav['twig'];
      $uri = $this->grav['uri'];

      if ( is_array($page->header()->simplecontact) || true === $page->header()->simplecontact ) {
        $config = array_merge($this->config->get('plugins.simplecontact'), (array) $page->header()->simplecontact);
      } else {
        return false;
      }

      if ( false === $uri->param('send') ) {
        if ( $_SERVER['REQUEST_METHOD'] == "POST") {
          if ( false === $this->validateFormData() ) {
            $this->grav->redirect($page->slug() . '/send:error');
          } else {
            if ( false === $this->sendEmail() ) {
              $this->grav->redirect($page->slug() . '/send:fail');
            } else {
              $this->grav->redirect($page->slug() . '/send:success');
            }
          }

        } else {
          $page->content($twig->twig()->render('simplecontact/form.html.twig', ['simplecontact' => $config, 'page' => $page]));
        }
      } else {
        switch ( $uri->param('send') ) {
          case 'success':
            $page->content($config['messages']['success']);
          break;

          case 'error':
            $page->content($config['messages']['error']);
          break;

          case 'fail':
            $page->content($config['messages']['fail']);
          break;

          default:
          break;
        }
      }
    }
  }

  protected function validateFormData()
  {
    $config = array_merge((array) $this->config->get('plugins.simplecontact'), (array) $this->grav['page']->header()->simplecontact);
    $form_data = $this->filterFormData($_POST);

    $name     = $form_data['name'];
    $email    = $form_data['email'];
    $message  = $form_data['message'];

    $antispam = $form_data['antispam'];

    if ( empty($name) || empty($message) || ! $email || $antispam ) {
      return false;
    } else {
      return true;
    }
  }

  protected function filterFormData($form)
  {
    $defaults = [
      'name'      => '',
      'email'     => '',
      'message'   => '',
      'antispam'  => ''
    ];

    $data = array_merge($defaults, $form);

    return [
      'name'      => $data['name'],
      'email'     => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
      'message'   => $data['message'],
      'antispam'  => $data['antispam']
    ];
  }

  protected function sendEmail()
  {
    $form   = $this->filterFormData($_POST);
    $config = array_merge($this->config->get('plugins.simplecontact'), $this->grav['page']->header()->simplecontact);

    $recipient  = $config['recipient'];
    $subject    = $config['subject'];

    $email_content = "Name: {$form['name']}\n";
    $email_content .= "Email: {$form['email']}\n\n";
    $email_content .= "Message:\n{$form['message']}\n";

    $email_headers = "From: {$form['name']} <{$form['email']}>";

    if ( mail($recipient, $subject, $email_content, $email_headers) ) {
      return true;
    } else {
      return false;
    }
  }
}
