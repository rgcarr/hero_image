This is a customised version of the [Responsive Background Image Module](https://www.drupal.org/project/responsive_background_image) to render a background image on each page.

This module  provides a method to call from within a preprocess function that will generate CSS media queries for a responsive background image for a specific HTML tag. This will allow you to serve a specific background image file depending on the browser window width, or the device pixel ratio. The provided method will return a style tag array ready to be inserted into the HTML `<head>` using `$variables['#attached']['html_head'][]` from within the preprocess function. It uses Drupal's Responsive Image Styles to generate the media queries and image paths. This module only supports a Media entity of type Image.

## How To Use

In your MYTHEME.theme file add an appropriate preprocess hook
```

/**
 * Implements hook_preprocess_HOOK() for page.html.twig.
 */
function MYTHEME_preprocess_page(array &$variables): void {
  $hero_id = theme_get_setting('hero_image');
  $css_selector = theme_get_setting('hero_selector');
  $responsive_set = theme_get_setting('hero_responsive');
  if (!empty($hero_id) && !empty($css_selector) && !empty($responsive_set)) {
    $style_tag = HeroImage::generateMediaQueries($css_selector, $hero_id, $responsive_set);
    if ($style_tag) {
      $variables['#attached']['html_head'][] = $style_tag;
    }
  }
}
```

To allow the image, CSS element to target and responsive image set to be configured add the following function to your MYTHEME.theme file:
```
/**
 * Implements hook_form_system_theme_settings_alter().
 */
function MYTHEME_form_system_theme_settings_alter(&$form, FormStateInterface $form_state, $form_id = NULL)
{
  // Work-around for a core bug affecting admin themes. See issue #943212.
  if (isset($form_id)) {
    return;
  }

  $form['MYTHEME_settings'] = [
    '#type' => 'details',
    '#title' => t('Site Hero'),
    '#description' => t('The Hero image is used in full behind the top of the home page.'),
    '#open' => TRUE,
  ];

  $form['MYTHEME_settings']['hero_image'] = [
    '#type' => 'media_library',
    '#allowed_bundles' => ['image'],
    '#title' => t('Hero image'),
    '#default_value' => theme_get_setting('hero_image'),
    '#description' => t('Upload or insert/select your Hero image.'),
  ];

  $form['MYTHEME_settings']['hero_selector'] = [
    '#type' => 'textfield',
    '#title' => t('Hero selector'),
    '#default_value' => theme_get_setting('hero_selector'),
    '#description' => t('CSS selector for Hero (include . and # as required)'),
  ];

  $form['MYTHEME_settings']['hero_responsive'] = [
    '#type' => 'textfield',
    '#title' => t('Hero responsive group'),
    '#default_value' => theme_get_setting('hero_responsive'),
    '#description' => t('The machine name of the <a href="@resp_settings">Responsive Image Style</a> to be used.', array('@resp_settings' => '/admin/config/media/responsive-image-style')),
  ];
}
```

And you'll need to add relevant CSS to your theme to ensure your targeted element renders a background image, for example:
```
body .main-canvas {
  background-repeat: no-repeat;
  background-position: top center;
  width: 100%;
  background-size: cover;
  max-width: 1920px;
  margin: 0 auto;
}
```

Adding a `height` value can help manage how much of the page has the background (use px, % or vh).

Additional guidance on the original module at https://www.drupal.org/docs/contributed-modules/responsive-background-image/how-to-use-the-responsive-background-image-module

## Acknowledgements
Responsive Background Image Module authors and maintainers:
* Ben Teegarden (maskedjellybean) https://www.drupal.org/u/maskedjellybean
* Julian Pustkuchen (Anybody) https://www.drupal.org/u/anybody
* Thomas Frobieter (thomas.frobieter) https://www.drupal.org/u/thomasfrobieter
