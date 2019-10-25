<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__('List Holidays'), ['controller' => 'Holidays', 'action' => 'index']) ?></li>
        <li><?= $this->Html->link(__('New Holiday'), ['controller' => 'Holidays', 'action' => 'add']) ?></li>
    </ul>
</nav>
<div class="remittanceBatch form large-9 medium-8 columns content">
    <?= $this->Form->create(null, ['id'=>'upform', 'type' => 'file', 'url' => ['controller' => 'Holidays', 'action' => 'add']]) ?>
    <fieldset>
        <legend><?= __('Add Holiday') ?></legend>
        <?php
            echo $this->Form->input('name',['type' => 'text','id'=>'name', 'required' => true ]);
            echo $this->Form->input('holiday_date',['type' => 'text','id'=>'holiday_date', 'required' => true ]);
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>


<script>
$(document).ready(function() {

    var holiday_date = $("[name=holiday_date]").kendoDatePicker({
        format: "yyyy-MM-dd",
    }).data("kendoDatePicker");

});
</script>
