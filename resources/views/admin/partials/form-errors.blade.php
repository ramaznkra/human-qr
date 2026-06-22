@if($errors->any())
<div class="admin-form-errors" role="alert">
    <p class="admin-form-errors__title">Formda düzeltilmesi gereken alanlar var</p>
    <ul class="admin-form-errors__list">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
