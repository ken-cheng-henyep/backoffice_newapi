<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <?php
            // = $this->Html->css('wc')
            $id = (isset($merchantArticle['id'])?$merchantArticle['id']:'');
    ?>
    <div id="errdialog"></div>
    <?= $this->Form->create(null, ['id'=>'upform', 'type' => 'file', 'url' => ['controller' => 'MerchantArticle', 'action' => 'edit', $id]]) ?>
    <fieldset>
        <legend><?= __('Add / Edit Documentation') ?></legend>
        <ul class="fieldlist">
            <li>
                <label for="simple-input">Section</label>
                <?=  $this->Form->select(
                'type',
                $type_lst,
                ['empty' => '(choose one)', 'required' => true, 'id'=>'typeDropdown', 'value'=> $merchantArticle['type']]); ?>
            </li>
            <li>
                <label for="simple-input">Title</label>
                <!--
                <input type="text" id="title" name="title" class="k-textbox" placeholder="" style="width: 500px;" />
                -->
                <?= $this->Form->input('title',['type' => 'text','label'=>false,'id'=>'title', 'required' => true, 'value'=> $merchantArticle['title'] ]) ?>
            </li>
            <li>
                <label for="">File</label>
                <?php if (empty($merchantArticle['filename'])): ?>
                    <?= $this->Form->file('upfile', ['id'=>'upfile','required' => true]) ?>
                <?php else: ?>
                    <?= $this->Form->input('cfile',['type' => 'text','label'=>false,'id'=>'cfile', 'required' => false, 'readonly'=>true, 'value'=> $merchantArticle['filename'] ]) ?>
                    <?= $this->Form->file('upfile', ['id'=>'upfile','required' => false]) ?>
                <?php endif; ?>
            </li>
            <li>
                <div id="buttonContainer">
                    <?= $this->Form->button(__('Save'), ['type' => 'submit', 'class'=>'kdbutton', 'href'=>"#", 'onclick'=>'uploadFile();', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-tick"]); ?>
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
            //$("#upfile").kendoUpload();
            $("#typeDropdown").kendoDropDownList();
            $("#typeDropdown").data("kendoDropDownList").list.width(200);
            kendo.init("#buttonContainer");

            $("#upfile").kendoUpload({
                validation: {
                    allowedExtensions: [".xls",".xlsx",".doc",".docx",".pdf",".zip"],
                    minFileSize: 1
                },
                multiple: false, showFileList: true
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
        });

        function popup(msg) {
            var dialog = $("#errdialog").data("kendoDialog");
            dialog.content(msg);
            dialog.open();
        }

        function onSelectFile(e) {
            var xls = $("#upfile").data("kendoUpload"),
                    pdf = $("#pdfile").data("kendoUpload");
            console.log(xls.getFiles().length);
            console.log(pdf.getFiles().length);
            if (xls.getFiles().length > 0 && pdf.getFiles().length > 0) {
                console.log("form good");
                $("#submit_btn").bind('click', function () {$("#upform").submit();});
            }
        };
        function uploadFile() {
            var xls = $("#upfile").data("kendoUpload"),
                    xles = xls.getFiles();
            if (xles.length > 0 && xles[0].validationErrors == null ) {
                //console.log("form good");
                $("#upform").submit();
            } else {
                console.log(xles.length);
                popup("Please provide missing file");
            }
        };
    </script>
</div>

<div>

</div>
