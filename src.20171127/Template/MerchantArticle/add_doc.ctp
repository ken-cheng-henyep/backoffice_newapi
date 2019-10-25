<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <?php // = $this->Html->css('wc') ?>
    <div id="errdialog"></div>
    <?= $this->Form->create(null, ['id'=>'upform', 'type' => 'file', 'url' => ['controller' => 'MerchantArticle', 'action' => 'addDoc']]) ?>
    <fieldset>
        <legend><?= __('Add Documentation') ?></legend>
        <ul class="fieldlist">
            <li>
                <label for="simple-input">Section</label>
                <?=  $this->Form->select(
                'type',
                $type_lst,
                ['empty' => '(choose one)', 'required' => true, 'id'=>'typeDropdown']); ?>
            </li>
            <li>
                <label for="simple-input">Title</label>
                <!--
                <input type="text" id="title" name="title" class="k-textbox" placeholder="" style="width: 500px;" />
                -->
                <?= $this->Form->input('title',['type' => 'text','label'=>false,'id'=>'title', 'required' => true ]) ?>
            </li>
            <li>
                <label for="">File</label>
                <?= $this->Form->file('upfile', ['id'=>'upfile','required' => true, ]) ?>
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
