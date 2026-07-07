<div class="grid cols-2">
    <div class="card">
        <h2>Vehicle Details</h2>
        <div class="form-grid">
            <div class="form-row"><label>Make</label><input type="text" name="make" value="{{ old('make',$vehicle->make) }}" required></div>
            <div class="form-row"><label>Model</label><input type="text" name="model" value="{{ old('model',$vehicle->model) }}" required></div>
            @if(\Illuminate\Support\Facades\Schema::hasColumn('vehicles','year_model'))
                <div class="form-row"><label>Year Model</label><input type="number" name="year_model" min="1900" max="{{ date('Y') + 1 }}" value="{{ old('year_model',$vehicle->year_model) }}" placeholder="2024"></div>
                <div class="form-row"><label>Colour</label><input type="text" name="colour" value="{{ old('colour',$vehicle->colour) }}" placeholder="White"></div>
            @endif
            <div class="form-row"><label>Current ODO</label><input type="number" name="odo" min="0" value="{{ old('odo',$vehicle->odo ?? 0) }}" required></div>
            <div class="form-row"><label>Status</label><select name="status"><option value="active" @selected(old('status',$vehicle->status)==='active')>Active</option><option value="inactive" @selected(old('status',$vehicle->status)==='inactive')>Inactive</option></select></div>
        </div>
        @if(\Illuminate\Support\Facades\Schema::hasColumn('vehicles','service_interval_km'))
            <div class="form-row"><label>Service Interval KM</label><input type="number" list="service_interval_options" name="service_interval_km" min="0" value="{{ old('service_interval_km',$vehicle->service_interval_km ?? 10000) }}" placeholder="Example: 10000"><datalist id="service_interval_options"><option value="5000"><option value="7500"><option value="10000"><option value="15000"><option value="20000"><option value="30000"></datalist><span class="muted small">Choose a common interval or type your own. Set to 0 if this vehicle should not trigger service reminders.</span></div>
            <div class="form-row"><label>Service Reminder KM Before Due</label><input type="number" list="service_reminder_options" name="service_reminder_km" min="0" value="{{ old('service_reminder_km',$vehicle->service_reminder_km ?? 1000) }}" placeholder="Example: 1000"><datalist id="service_reminder_options"><option value="500"><option value="1000"><option value="1500"><option value="2000"><option value="3000"><option value="5000"></datalist><span class="muted small">Choose how many kilometres before the service due ODO the reminder should appear.</span></div>
        @endif
    </div>
    <div class="card">
        <h2>Registration & Fuel Matching</h2>
        <p class="muted small">Use Vehicle CSV Name for fuel exports where the vehicle is identified by a short name such as the CSV <strong>car_name</strong> column.</p>
        <div class="form-row"><label>Registration Number</label><input type="text" name="registration_number" value="{{ old('registration_number',$vehicle->registration_number) }}"></div>
        <div class="form-row"><label>Vehicle CSV Name / Nickname</label><input type="text" name="vehicle_key" value="{{ old('vehicle_key',$vehicle->vehicle_key) }}" placeholder="Example: Attie"></div>
        @if(\Illuminate\Support\Facades\Schema::hasColumn('vehicles','tracking_company_name'))
            <h2>Tracking Company</h2>
            <div class="form-row"><label>Tracking Company Name</label><input type="text" name="tracking_company_name" value="{{ old('tracking_company_name',$vehicle->tracking_company_name) }}" placeholder="Example: Cartrack"></div>
            <div class="form-row"><label>Tracking Company Contact</label><input type="text" name="tracking_company_contact" value="{{ old('tracking_company_contact',$vehicle->tracking_company_contact) }}" placeholder="Phone or support details"></div>
            <div class="form-row"><label>Tracking Device / Account Number</label><input type="text" name="tracking_device_number" value="{{ old('tracking_device_number',$vehicle->tracking_device_number) }}"></div>
            @if(\Illuminate\Support\Facades\Schema::hasColumn('vehicles','cartrack_vehicle_id'))
                <div class="form-row"><label>Cartrack Vehicle ID</label><input type="text" name="cartrack_vehicle_id" value="{{ old('cartrack_vehicle_id',$vehicle->cartrack_vehicle_id) }}" placeholder="Optional API vehicle ID"></div>
                <div class="form-row"><label>Cartrack Registration</label><input type="text" name="cartrack_registration" value="{{ old('cartrack_registration',$vehicle->cartrack_registration) }}" placeholder="Used for API matching"></div>
                <div class="form-row"><label>Cartrack External Key</label><input type="text" name="cartrack_external_key" value="{{ old('cartrack_external_key',$vehicle->cartrack_external_key) }}"></div>
            @endif
            <div class="form-row"><label>Tracking Notes</label><textarea name="tracking_notes">{{ old('tracking_notes',$vehicle->tracking_notes) }}</textarea></div>
        @endif
        <div class="form-row"><label>Vehicle Notes</label><textarea name="notes">{{ old('notes',$vehicle->notes) }}</textarea></div>
    </div>
</div>
<div style="height:14px"></div>
<div class="actions"><button class="btn primary" type="submit">Save Vehicle</button><a class="btn" href="{{ route('vehicles.index') }}">Cancel</a></div>
