<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $uploadActivity->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $uploadActivity->id)]
            )
        ?></li>
        <li><?= $this->Html->link(__('List Upload Activity'), ['action' => 'index']) ?></li>
    </ul>
</nav>
<div class="uploadActivity form large-9 medium-8 columns content">
    <?= $this->Form->create($uploadActivity) ?>
    <fieldset>
        <legend><?= __('Edit Upload Activity') ?></legend>
        <?php
            echo $this->Form->input('status');
            echo $this->Form->input('merchant');
            echo $this->Form->input('currency');
            echo $this->Form->input('upload_time');
            echo $this->Form->input('tx_time', ['empty' => true]);
            echo $this->Form->input('source_file');
            echo $this->Form->input('output_file');
            echo $this->Form->input('json_file');
            echo $this->Form->input('username');
            echo $this->Form->input('ip_addr');
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
