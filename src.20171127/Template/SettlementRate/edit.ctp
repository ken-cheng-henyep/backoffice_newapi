<div class="users form">
    <?= $this->Flash->render('auth') ?>
    
    <div id="errdialog"></div>
    <?= $this->Form->create(null, ['url' => [ 'action' => 'edit', $entity['id']]]) ?>
    <fieldset>
        <legend><?= __('Edit Settlement Rate Symbol') ?></legend>
        <ul class="fieldlist">

            <li>
                <label for="simple-input">Symbol</label>
                <span><?php echo $entity['rate_symbol']?></span>
            </li>
            <li>
                <?= $this->Form->input('description',['type' => 'text', 'required' => true, 'value'=>$entity['description'], 'maxlength'=>'200' ]) ?>
            </li>
            <li>
                <div id="buttonContainer">
                    <?= $this->Form->button(__('Save'), ['type' => 'submit', 'class'=>'k-button left', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-tick"]); ?>
                    <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'k-button left', 'data-role'=>"button", 'data-icon'=>"cancel"] ); ?>
                </div>
            </li>
        </ul>
    </fieldset>
    
    <?= $this->Form->end() ?>
<div>