<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <div id="errdialog"></div>
    <fieldset>
        <legend><?= __($pageTitle) ?></legend>
    </fieldset>
    <div>
        <?php
                $action = ($isNews?'editNews':'edit');
                $url = $this->Url->build([ "action" => $action,]);
                print $this->Form->button('Add', ['type' => 'button', 'id'=>'add_btn', 'onclick'=> "window.location.href = '$url';"]);
        ?>
    </div>
    <div id="grid"></div>
</div>

<script>
$(document).ready(function() {
    var dataSource = new kendo.data.DataSource({
        serverFiltering: true,
        transport: {
            read: {
                url:'<?=$this->Url->build([ "action" => "jsonList", ($isNews?"news":"docs")])?>',
                dataType: "json",

            },
        },
        success: function (data) {
         //   console.log('success');
        },
        change: function (data) {
            console.log('change');
            /*
            console.log('all_log_set:'+data.items[0].all_log_set);
            $('#log_ready').val(data.items[0].all_log_set);
            onTBChange();
            */
        },
        schema: {
            data: "data",
            total: "total",
            model: {
                fields: {
                    create_time: { type: "date" },
                    content_time: { type: "date" },
                }
            }
        },
        pageSize: 25,
        sortable: true,
        serverPaging: false,
        serverSorting: false,
        //sort: { field: "id", dir: "desc" }
    });

    $("#grid").kendoGrid({
        columns: [
            {field: "section",title: "Section", width: 50, attributes:{}},
            {field: "create_time",title: "Date", width: 60, format: "{0:yyyy-MM-dd}"},
            {field: "content_time",title: "News Date", width: 60, format: "{0:yyyy-MM-dd}"},
            {field: "title",title: "Title", width: 200},
            {field: "filename",title: "File", width: 120},
            {field: "status_txt",title: "Status", width: 50, attributes:{}},
            {
                field: "",
                title: "Edit",
                width: 40,
                sortable: false,
                template: "<a class='' href=\"${action_url}\" >Edit</a>"
            },
            {
                field: "action",
                title: "Actions",
                width: 40,
                sortable: false,
                template: "<a class='' href=\"javascript:void(0);\" data-id='${id}' onclick=\"updateDoc('${id}','${action_status}');\">${action_txt}</a>"
            }
        ],
// javascript:void(0);
// data-id=${id}, data-bid=${batch_id}
        dataSource: dataSource,
        //height: 420,
        /*change: onGridChange, */
        selectable: "row",
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        pageable: {
            refresh: true,
            //pageSizes: true,
            pageSizes: [10, 25, 50, "All"],
            buttonCount: 2
        },
        filterable: false,
        resizable: true,
        //scrollable: false,
    });

<?php if ($isNews) : ?>
    $('#grid').data("kendoGrid").hideColumn("section");
    $('#grid').data("kendoGrid").hideColumn("filename");
<?php else: ?>
    $('#grid').data("kendoGrid").hideColumn("content_time");
<?php endif; ?>

});

function popup(msg) {
    var dialog = $("#errdialog").data("kendoDialog");
    dialog.content(msg);
    dialog.open();
}
function updateDoc(id, status) {
    //url = 'update-status';
    url = '<?=$this->Url->build([ "action" => "updateStatus" ])?>'
    console.log("updateDoc("+id+", "+status+") url:"+url);
    //return true;

    $.post(url,'id='+id+'&status='+status, function(data) {
        console.log("return:"+data.status);
        if (data.status==0) {
            //success
            $('#grid').data('kendoGrid').dataSource.read();
            //$('#GridName').data('kendoGrid').refresh();
            return true;
        }
    }, 'json');

    console.log('end post');
    return false;
}
</script>
<style>
#grid > .k-grid-header > div > table,
#grid > .k-grid-content > table
{
    width: 100% !important;
}
</style>