<div class="row g-2 mb-2" data-repeater-item>
    <div class="col-md-5">
        <select class="form-select form-select-sm" name="{{ $field ?? 'new_documents' }}[{{ $i }}][type]">
            @foreach (\App\Models\ClientDocument::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-6"><input type="file" class="form-control form-control-sm" name="{{ $field ?? 'new_documents' }}[{{ $i }}][file]" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
    <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove><i class="bi bi-x"></i></button></div>
</div>
