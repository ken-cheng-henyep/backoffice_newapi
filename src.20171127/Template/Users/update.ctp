<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <?= $this->Form->create() ?>
    <fieldset>
        <legend><?= __('Please enter your old and new password') ?></legend>
        <?= $this->Form->input('username', ['default' => $user['username'], 'readonly'=>true ]) ?>
        <?= $this->Form->input('password', ['required' => true, 'label' => 'Current Password']) ?>
        <?= $this->Form->input('new_password', ['type' => 'password', 'required' => true, 'label'=>'New Password']) ?>
        <?= $this->Form->input('new_password_confirm', ['type' => 'password', 'required' => true, 'label'=>'Confirm New Password']) ?>
    </fieldset>
    <?= $this->Form->button(__('Update')); ?>
    <?= $this->Form->button(__('Reset'), ['type' => 'reset']); ?>
    <?= $this->Form->end() ?>
</div>