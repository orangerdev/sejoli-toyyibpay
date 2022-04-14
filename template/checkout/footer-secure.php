<div class="footer-secure">
    <p>
        <span class="secure-tagline-img"><img src="<?php echo SEJOLISA_URL; ?>public/img/shield.png"> <?php _e('Informasi Pribadi Anda Aman', 'sejoli-toyyibpay'); ?></span>
        <?php if(false !== boolval(carbon_get_the_post_meta('display_warranty_label'))) : ?>
        <span class="secure-tagline-img"><img src="<?php echo SEJOLISA_URL; ?>public/img/guarantee.png"> <?php _e('100% Garansi Uang Kembali', 'sejoli-toyyibpay'); ?></span>
        <?php endif; ?>
    </p>
</div>
