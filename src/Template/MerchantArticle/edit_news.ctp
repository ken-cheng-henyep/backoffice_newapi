<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <?php
            // = $this->Html->css('wc')
            $id = (isset($merchantArticle['id'])?$merchantArticle['id']:'');
            ?>
    <div id="errdialog"></div>
    <?= $this->Form->create(null, ['id'=>'upform', 'url' => ['controller' => 'MerchantArticle', 'action' => 'edit_news', $id]]) /* 'type' => 'file', */?>
    <fieldset>
        <legend><?= __('Add / Edit News') ?></legend>
        <ul class="fieldlist">
            <li>
                <label for="simple-input">Date</label>
                <input id="ndate" name="ndate" required="true" value="<?= (empty($merchantArticle['content_date'])?date('Y/m/d'):$merchantArticle['content_date']) ?>"/>
            </li>
            <li>
                <label for="simple-input">Title</label>
                <!--
                <input type="text" id="title" name="title" class="k-textbox" placeholder="" style="width: 500px;" />
                -->
                <?= $this->Form->input('ntitle',['type' => 'text','label'=>false,'id'=>'ntitle', 'required' => true, 'value'=> $merchantArticle['title'] ]) ?>
            </li>
            <li>
                <label for="">Content</label>
                <div id="editor">
                    <textarea id="neditor" name="neditor" rows="10" cols="30"><?= (isset($merchantArticle['content'])?$merchantArticle['content']:'')?></textarea>
                </div>
            </li>
            <li>
                <div id="buttonContainer">
                    <?= $this->Form->button(__('Save'), ['type' => 'button', 'class'=>'kdbutton', 'href'=>"#", 'onclick'=>'submitForm();', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-tick"]); ?>
                    <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"cancel"] ); ?>
                </div>
            </li>
        </ul>
    </fieldset>

    <div>

    </div>
    <?= (isset($merchantArticle['id'])?$this->Form->hidden('id', ['id'=>'id', 'value'=>$merchantArticle['id'] ]):''); ?>
    <?= $this->Form->end() ?>
    <script>
        $(document).ready(function() {
            var datepick = $("#ndate").kendoDatePicker({
                format: "yyyy/MM/dd"
                //change: startChange
            }).data("kendoDatePicker");

            $("#neditor").kendoEditor({
                tools: [
                    "bold","italic","underline", "fontSize", "foreColor", "createLink", "unlink",
                ]
            });
            //error dialog
            $("#errdialog").kendoDialog({
                width: 250,
                title: "Error",
                closable: false,
                visible: false,
                actions: [
                    { text: 'OK', primary: true }
                ]
            }).data("kendoDialog");
            kendo.init("#buttonContainer");
            console.log("ready fin");
        });

        function popup(msg) {
            var dialog = $("#errdialog").data("kendoDialog");
            dialog.content(msg);
            dialog.open();
        }

        function submitForm() {
            console.log("submitForm");
            var datepicker = $("#ndate").data("kendoDatePicker");
            var editor = $("#neditor").data("kendoEditor");
            var ntitle = $("#ntitle").val();
            //console.log("form good:"+datepicker.value());

            if (ntitle.trim() && datepicker.value()!= null && editor.value().trim()) {
                console.log("form good");
                $("#upform").submit();
                return true;
            } else {
                //console.log(ntitle);
                //console.log('content:'+editor.value());
                popup("All fields are mandatory, please enter the empty field.");
            }
            return false;
        };
    </script>
</div>

<div>

</div>
