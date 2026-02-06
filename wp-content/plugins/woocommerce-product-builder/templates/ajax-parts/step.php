<?php defined( 'ABSPATH' ) || exit; ?>

<div class="woopb-step">
    <div class="woopb-step-header">
        <div class="woopb-step-title"></div>
        <div class="woopb-step-desc"></div>
    </div>
    <div class="woopb-products">
    </div>
    <div class="woopb-step-footer">
		<?php
		if ( $enable_multi_select ) {
			?>
            <span class="woopb-load-step woopb-load-step-outer woopb-button woopb-button-primary"></span>
			<?php
		}
		?>
    </div>
</div>