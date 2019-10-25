<?php
        $class = 'message';
        if (!empty($params['class'])) {
        $class .= ' ' . $params['class'];
        }
        ?>
<div class="<?= h($class) ?>"><?= h($message) ?></div>
<div>
<?= $this->Form->create(null, ['type' => 'file', 'url' => ['controller' => 'RemittanceBatch', 'action' => 'upload']]) ?>
<?php
        /*
        $this->log($this->request->data, 'info');
        foreach ($this->request->data as $k=>$v)
        $this->Form->hidden($k, ['value'=>$v]);
        */
        $this->log("session:", 'info');
        $this->log($this->request->session()->read('WC.formData'), 'info');
        ?>
<?= $this->Form->hidden('confirm', ['value'=>'true']); ?>
<div align='center'>
    <?= $this->Form->button(__('Confirm'), ['type'=>'submit']); ?>
    <?= $this->Html->link("Cancel", ['controller' => 'RemittanceBatch', 'action' => 'upload'], array( 'class' => 'button')); ?>
</div>
<?= $this->Form->end() ?>
</div>