<!DOCTYPE html>
<?php
global $sejolisa;

$order = $sejolisa['order'];

if( !isset( $order['product_id'] ) ) :
    wp_die(
        __('Order tidak ada.', 'sejoli-toyyibpay'),
        __('Terjadi kesalahan', 'sejoli-toyyibpay')
    );
endif;

$order = $sejolisa['order'];
?>
<html>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php _e('Terima kasih', 'sejoli-toyyibpay'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php wp_head(); ?>

    <?php
    $inline_styles = '';

    $attachment_id = intval( carbon_get_post_meta( $order['product_id'], 'desain_bg_image' ) );
    if ( empty( $attachment_id ) ) :
        $attachment_id = intval( carbon_get_theme_option( 'desain_bg_image' ) );
    endif;
    $desain_bg_image = wp_get_attachment_url( $attachment_id );
    if ( !empty( $desain_bg_image ) ) :
        $inline_styles .= 'background-image: url('.$desain_bg_image.');';
    endif;

    $desain_bg_color = carbon_get_post_meta( $order['product_id'],'desain_bg_color' );
    if ( empty( $desain_bg_color ) ) :
        $desain_bg_color = carbon_get_theme_option( 'desain_bg_color' );
    endif;
    if ( !empty( $desain_bg_color ) ) :
        $inline_styles .= 'background-color: '.$desain_bg_color.' !important;';
    endif;

    $desain_bg_repeat = carbon_get_post_meta( $order['product_id'],'desain_bg_repeat' );
    if ( empty( $desain_bg_repeat ) ) :
        $desain_bg_repeat = carbon_get_theme_option( 'desain_bg_repeat' );
    endif;
    if ( !empty( $desain_bg_repeat ) ) :
        $inline_styles .= 'background-repeat: '.$desain_bg_repeat.';';
    endif;

    $desain_bg_size = carbon_get_post_meta( $order['product_id'],'desain_bg_size' );
    if ( empty( $desain_bg_size ) ) :
        $desain_bg_size = carbon_get_theme_option( 'desain_bg_size' );
    endif;
    if ( !empty( $desain_bg_size ) ) :
        $inline_styles .= 'background-size: '.$desain_bg_size.';';
    endif;

    $desain_bg_position = carbon_get_post_meta( $order['product_id'],'desain_bg_position' );
    if ( empty( $desain_bg_position ) ) :
        $desain_bg_position = carbon_get_theme_option( 'desain_bg_position' );
    endif;
    if ( !empty( $desain_bg_position ) ) :
        $inline_styles .= 'background-position: '.$desain_bg_position.';';
    endif;

    sejoli_get_template_part( 'fb-pixel/thankyou-page.php' );
    ?>
</head>
<body class="body-checkout" style="<?php echo $inline_styles; ?>">
