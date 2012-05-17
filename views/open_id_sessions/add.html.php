<?=$this->form->create(null); ?>
    <?=$this->form->field('openid_identifier'); ?>
    <?=$this->form->submit('Log In With Open Id'); ?>
<?=$this->form->end(); ?>

<?php if ($error) : ?>
    <span class="error">Sorry, there was a problem logging you in</span>
<?php endif; ?>
