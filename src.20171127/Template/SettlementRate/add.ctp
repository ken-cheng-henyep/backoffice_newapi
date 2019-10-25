<div class="users form">
    <?= $this->Flash->render('auth') ?>

    <div id="errdialog"></div>
    <?= $this->Form->create(null, ['url' => [ 'action' => 'add']]) ?>
    <fieldset>
        <legend><?= __('Add Settlement Rate Symbol') ?></legend>
        <ul class="fieldlist">

            <li>
                <?= $this->Form->input('rate_symbol',['type' => 'text','required' => true, 'maxlength'=>'24' ]) ?>
            </li>
            <li>
                <?= $this->Form->input('rate_value',['type' => 'number', 'step'=>'0.0001','min'=>'0','required' => true ]) ?>
            </li>
            <li>
                <?= $this->Form->input('description',['type' => 'text','required' => true, 'maxlength'=>'200' ]) ?>
            </li>
            <li>
                <div id="buttonContainer">
                    <?= $this->Form->button(__('Save'), ['type' => 'submit', 'class'=>'k-button left', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-tick"]); ?>
                    <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'k-button right', 'data-role'=>"button", 'data-icon'=>"cancel"] ); ?>
                </div>
            </li>
        </ul>
    </fieldset>

    <?= $this->Form->end() ?>
<div>