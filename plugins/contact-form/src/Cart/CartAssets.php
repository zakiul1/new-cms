<?php

namespace Plugins\ContactForm\Cart;

class CartAssets
{
  private static bool $fallbackInjected = false;

  public static function enqueue(): void
  {
    $cssUrl = asset('_contact/cart.css');
    $jsUrl = asset('_contact/cart.js');

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
        // fall through to fallback injection
      }
    }

    self::fallbackInjectTags($cssUrl, $jsUrl);
  }

  private static function fallbackInjectTags(string $cssUrl, string $jsUrl): void
  {
    if (self::$fallbackInjected) {
      return;
    }

    self::$fallbackInjected = true;

    if (!function_exists('add_action')) {
      return;
    }

    add_action('cms.head', function () use ($cssUrl) {
      echo '<link rel="stylesheet" href="' . e($cssUrl) . '">';
    }, 99);

    add_action('cms.footer', function () use ($jsUrl) {
      echo '<script src="' . e($jsUrl) . '" defer></script>';
    }, 99);
  }
}