<?php

namespace Plugins\ContactForm\Cart;

class CartAssets
{
  public static function enqueue(): void
  {
    self::enqueueNow();
  }

  private static function enqueueNow(): void
  {
    // Static files served directly by web server from /public/_contact/
    $cssUrl = asset('_contact/cart.css');
    $jsUrl = asset('_contact/cart.js');

    // Provide cssUrl to JS (used by ensureAssetsLoaded() inside cart.js)
    self::injectConfig($cssUrl);

    if (function_exists('cms_enqueue_style')) {
      cms_enqueue_style('contact-form-cart', $cssUrl);
    }
    if (function_exists('cms_enqueue_script')) {
      cms_enqueue_script('contact-form-cart', $jsUrl);
    }

    if (function_exists('cms_assets')) {
      try {
        $assets = cms_assets();
        if ($assets) {
          if (method_exists($assets, 'enqueueStyle')) {
            $assets->enqueueStyle('contact-form-cart', $cssUrl);
          }
          if (method_exists($assets, 'enqueueScript')) {
            $assets->enqueueScript('contact-form-cart', $jsUrl, ['defer' => 'defer']);
          }
          return;
        }
      } catch (\Throwable $e) {
        // fall through
      }
    }

    self::fallbackInjectTags($cssUrl, $jsUrl);
  }

  private static bool $fallbackInjected = false;

  private static function fallbackInjectTags(string $cssUrl, string $jsUrl): void
  {
    if (self::$fallbackInjected)
      return;
    self::$fallbackInjected = true;

    if (function_exists('add_action')) {
      add_action('cms.head', function () use ($cssUrl) {
        echo '<link rel="stylesheet" href="' . e($cssUrl) . '">';
      }, 99);

      add_action('cms.footer', function () use ($jsUrl) {
        echo '<script src="' . e($jsUrl) . '" defer></script>';
      }, 99);
    }
  }

  private static bool $configInjected = false;

  private static function injectConfig(string $cssUrl): void
  {
    if (self::$configInjected)
      return;
    self::$configInjected = true;

    if (!function_exists('add_action'))
      return;

    add_action('cms.head', function () use ($cssUrl) {
      echo '<script>window.ContactFormCart=' . json_encode([
        'cssUrl' => $cssUrl,
      ]) . ';</script>';
    }, 10);
  }
}