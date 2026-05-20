<div class="modal fade" id="confirm-delete-alert-template" tabindex="-1" role="dialog" aria-labelledby="Delete">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h5 class="modal-title" id="Delete">Confirm Delete</h5>
            </div>
            <div class="modal-body">
                <p>If you would like to remove the alert template then please click Delete.</p>
            </div>
            <div class="modal-footer">
                <form role="form" class="remove_alert_templet_form">
                    <?php echo csrf_field() ?>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger danger" id="alert-template-removal" data-target="alert-template-removal">Delete</button>
                    <input type="hidden" name="template_id" id="template_id" value="">
                    <input type="hidden" name="confirm" id="confirm" value="yes">
                </form>
            </div>
        </div>
    </div>
</div>


@push('scripts')
    <script>
        $('#alert-template-removal').on("click", function(event) {
            event.preventDefault();
            var template_id = $("#template_id").val();
            $.ajax({
                type: 'DELETE',
                url: '<?php echo route('alert-templates.destroy', ':template') ?>'.replace(':template', template_id),
                success: function (response) {
                    $('[data-row-id="' + template_id + '"]').remove();

                    toastr.success(response.message);

                    $('#confirm-delete-alert-template').modal('hide');
                    $('#template_id').val('');
                },
                error: function () {
                    toastr.error('The alert template could not be deleted.');

                    $('#confirm-delete-alert-template').modal('hide');
                }
            });
        });

        $('#confirm-delete-alert-template').on('hide.bs.modal', function(event) {
            $('#template_id').val('');
        });
    </script>
@endpush
