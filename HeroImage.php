<?php

namespace Drupal\hero_image;

use Drupal\media\Entity\Media;
use Drupal\image\Entity\ImageStyle;
use Drupal\file\Entity\File;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Core\Render\Markup;

/**
 * Provides a method to generate a responsive background image.
 *
 * Provides a method to generate a responsive background image
 * using a Responsive Image Style.
 */
class HeroImage {

  /**
   * Generates a Drupal style tag array containing CSS media queries.
   *
   * Generates a Drupal style tag array containing CSS media queries to apply a
   * responsive background image to a specific CSS Element.
   * The return value must be assigned correctly. See the return
   * description below.
   *
   * @param string $css_selector
   *   A CSS selector that points to the HTML tag to which a background image
   *   will be applied. Do not include curly braces/brackets.
   *   This selector should be unique to avoid the same background image
   *   being accidentally applied to multiple elements. For example,
   *   if you have a Paragraph type of Hero, you should add a class
   *   containing the entity ID to the Paragraph template, and then pass in
   *   that class as part of the selector here. This way multiple instances of
   *   the Hero Paragraph can appear on the same page with
   *   different background images.
   *   For example: '.paragraph--id--3 .hero__image', where '3' is the entity
   *   ID retrieved from the entity using $paragraph_entity->id().
   * @param string $field_target_id
   *   ID of the Media image to be used as the background.
   * @param string $responsive_image_style_machine_name
   *   The machine name of the Responsive Image Style to be used.
   * @param string $media_entity_field_machine_name
   *   Optional. If the image field is a Media field, and the Image field on the
   *   Image Media Type is custom and not the default 'field_media_image',
   *   pass in the custom field machine name.
   *
   * @return array
   *   A Drupal style tag array containing CSS media queries to apply
   *   responsive background images to a specific HTML tag. Assuming that this
   *   method has been called from inside a preprocess function such as
   *   THEME_preprocess_page(&$variables), the return value should be assigned
   *   to $variables['#attached']['html_head'][], or else calling this method
   *   will have no effect. Returns false if media queries cannot be generated.
   */
  public static function generateMediaQueries(string $css_selector, string $field_target_id, string $responsive_image_style_machine_name, string $media_entity_field_machine_name = 'field_media_image') {
    if (empty($field_target_id)) {
      \Drupal::logger('responsive_background_image')->error('Responsive Background Image could not be loaded as target_id was empty.');
      return FALSE;
    }

    // Following based on Media Image field.
    $moduleHandler = \Drupal::service('module_handler');
    $fileId = NULL;
    if ($moduleHandler->moduleExists('media')) {
      $media_entity = Media::load($field_target_id);
      if (empty($media_entity)) {
        \Drupal::logger('hero_image')->error('Hero Background Image referenced media with target_id: @target_id could not be loaded. Has the referenced media been deleted?', ['@target_id' => $field_target_id]);
        return FALSE;
      }
      $fileId = $media_entity->get($media_entity_field_machine_name)->getValue()[0]['target_id'];
    }

    $file_entity = File::load($fileId);
    $uri = $file_entity->getFileUri();

    // Load Responsive Image Style and mappings.
    $responsiveImageStyle = ResponsiveImageStyle::load($responsive_image_style_machine_name);
    $image_style_mappings = array_reverse($responsiveImageStyle->getImageStyleMappings());

    // Load theme breakpoints.
    $breakpoint_group = $responsiveImageStyle->getBreakpointGroup();
    $breakpoints = \Drupal::service('breakpoint.manager')
      ->getBreakpointsByGroup($breakpoint_group);

    $media_queries_1x = '';
    $media_queries_2x = '';

    // If a fallback image style is set,
    // add a default background image to media query.
    $fallback_image_style = $responsiveImageStyle->getFallbackImageStyle();
    switch ($fallback_image_style) {
      case '_empty image_':
        break;

      case '_original image_':
        $media_queries_1x .= self::createFallbackMediaQuery($css_selector, \Drupal::service('file_url_generator')->transformRelative($file_entity->createFileUrl()));
        break;

      default:
        $media_queries_1x .= self::createFallbackMediaQuery($css_selector, \Drupal::service('file_url_generator')->transformRelative(ImageStyle::load($responsiveImageStyle->getFallbackImageStyle())
          ->buildUrl($uri)));
        break;
    }

    // Loop through image style mappings starting from smallest to largest
    // and build media queries.
    foreach ($image_style_mappings as $image_style_mapping) {
      // Load media query for breakpoint.
      $media_query = $breakpoints[$image_style_mapping['breakpoint_id']]->getMediaQuery();

      // Get path to image using image style.
      $image_style = $image_style_mapping['image_mapping'];
      switch ($image_style) {
        case '_empty image_':
          continue 2;

        case '_original image_':
          $image_path = \Drupal::service('file_url_generator')->transformRelative($file_entity->createFileUrl());
          break;

        default:
          $image_path = \Drupal::service('file_url_generator')->transformRelative(ImageStyle::load($image_style)->buildUrl($uri));
          break;
      }

      // If multiplier is 1x.
      if ($image_style_mapping['multiplier'] == '1x') {
        $media_queries_1x .= self::createSingleMediaQuery($media_query, $css_selector, $image_path, '1x');
      }
      // If multiplier is greater than 1x.
      // @todo Should we actually pass the exact multiplier to the media query?
      else {
        $media_queries_2x .= self::createSingleMediaQuery($media_query, $css_selector, $image_path, '2x');
      }
    }

    $all_media_queries = $media_queries_1x . $media_queries_2x;
    // Return style tag array.
    return [
      [
        '#tag' => 'style',
        // We have to add this as insecure markup, otherwise auto-escaping
        // escapes the & to &amp; and breaks image urls.
        // See https://www.drupal.org/project/responsive_background_image/issues/3067838#comment-13208830
        '#value' => Markup::create($all_media_queries),
        // Should be last element of <head>, currently impossible
        // due to core issue #2391025, so we'll at least set this as
        // high as possible behind the meta tags,
        // but it won't get behind the <title>.
        '#weight' => 99999,
      ],
      'responsive-hero-image',
    ];
  }

  /**
   * Creates a fallback media query.
   *
   * @param string $css_selector
   *   CSS selector for element that will have background image.
   * @param string $fallback_image_path
   *   Path to the fallback image.
   *
   * @return string
   *   A CSS property for a fallback background image.
   */
  private static function createFallbackMediaQuery(string $css_selector, string $fallback_image_path) {
    return '
    ' . $css_selector . ' {
      background-image: url(' . $fallback_image_path . ');
    }';
  }

  /**
   * Creates a single media query.
   *
   * @param string $media_query
   *   CSS media query from Breakpoint group config.
   * @param string $css_selector
   *   CSS selector for element that will have background image.
   * @param string $image_path
   *   Path to image.
   * @param string $multiplier
   *   Responsive image multiplier/pixel ratio.
   *
   * @return string
   *   A single CSS media query for one one window width
   *   and one multiplier/pixel ratio.
   */
  private static function createSingleMediaQuery(string $media_query, string $css_selector, string $image_path, string $multiplier) {
    switch ($multiplier) {
      case '1x':
        $min_pixel_ratio = '';
        break;

      default:
        $min_pixel_ratio = 'and (min-device-pixel-ratio: 1.5)';
        break;
    }

    return '
    @media ' . $media_query . $min_pixel_ratio . ' {
      ' . $css_selector . ' {
        background-image: url(' . $image_path . ');
      }
    }';
  }

}
