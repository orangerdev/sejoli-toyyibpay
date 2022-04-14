<div class="header-logo">
    <div class="ui text container">
    <?php
        if ( isset( $order['product_id'] ) && !empty( $order['product_id'] ) ) :
            $product_id = $order['product_id'];
        else:
            $product_id = get_the_ID();
        endif;

        $logo = sejolisa_desain_logo_url( $product_id );
        
        if( !empty( $logo ) ) :
            echo '<img src="'.$logo.'" alt="" title=""/>';
        endif;
    ?>
    </div>
</div>