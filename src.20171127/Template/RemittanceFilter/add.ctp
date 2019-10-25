<?php
/**
  * @var \App\View\AppView $this
  */
?>
<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__('List Remittance Filter'), ['action' => 'index']) ?></li>
    </ul>
</nav>
<div class="remittanceFilter form large-9 medium-8 columns content">
    <?= $this->Form->create($remittanceFilter) ?>
    <fieldset>
        <legend><?= __('Add Remittance Filter') ?></legend>
        <?php
            echo $this->Form->input('name');
            echo $this->Form->input('dsc');
            echo $this->Form->input('code');
            echo $this->Form->input('merchant_id');
            echo $this->Form->input('rule_type');
            echo $this->Form->input('action');
            echo $this->Form->input('isblacklist');
            echo $this->Form->input('count_limit');
            echo $this->Form->input('amount_limit');
            echo $this->Form->input('period');
            echo $this->Form->input('username');
            echo $this->Form->input('remarks');
            echo $this->Form->input('status');
            echo $this->Form->input('create_time');
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
