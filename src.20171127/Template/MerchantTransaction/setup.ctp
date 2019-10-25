<div class="remittanceBatch index large-12 medium-10 columns content">
    <h3><?= __('Instant Transaction Setup') ?></h3>
    <div class="users form">
        <?= $this->Flash->render('auth') ?>
        <?= $this->Form->create(null, ['url' => ['controller' => 'MerchantTransaction', 'action' => 'setup']]) ?>
        <ul class="fieldlist">
            <li>
                Preferred Processor: <?=$first['name'] ?>
            </li>
            <li>
                <label for="">Change to:</label>
                <?=  $this->Form->select('processor',
                $processors_lst,
                ['empty' => '(choose one)', 'required' => true, 'id'=>'psDropdown', ]); ?>
            </li>
            <li>
                <div id="buttonContainer">
                    <?= $this->Form->button(__('Update'), ['type' => 'submit', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search" ]); ?>
                    <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'kdbutton', 'data-role'=>"button", 'onclick'=>'', 'data-icon'=>""]); ?>
                </div>
            </li>
        </ul>
        <?= $this->Form->end() ?>
    </div>
    <div>&nbsp</div>
</div>