@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8 col-md-10 mx-auto">
            <div class="card shadow-lg border-radius-lg">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Edit Lead Details</h6>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('leads.update', $lead->id) }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group input-group-static mb-4">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $lead->first_name) }}" placeholder="Enter first name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group input-group-static mb-4">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $lead->last_name) }}" placeholder="Enter last name">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group input-group-static mb-4">
                                    <label>Email Address</label>
                                    <input type="email" name="email" class="form-control" value="{{ old('email', $lead->email) }}" placeholder="example@email.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group input-group-static mb-4">
                                    <label>Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $lead->phone) }}" placeholder="(555) 000-0000">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group input-group-static mb-4">
                                    <label>Date of Birth</label>
                                    <input type="date" name="dob" class="form-control" value="{{ old('dob', $lead->dob) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group input-group-static mb-4">
                                    <label>Lead Source</label>
                                    <input type="text" name="source" class="form-control" value="{{ old('source', $lead->source) }}" placeholder="e.g. Website, Referral, Social Media">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group input-group-static mb-4">
                                    <label>ID Expiry Date</label>
                                    <input type="date" name="id_expiry" class="form-control" value="{{ old('id_expiry', $lead->id_expiry) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group input-group-static mb-4">
                                    <label>Champs Association Date</label>
                                    <input type="date" name="champs_association_date" class="form-control" value="{{ old('champs_association_date', $lead->champs_association_date) }}">
                                </div>
                            </div>
                        </div>

                        <div class="input-group input-group-static mb-4">
                            <label>Scan ID / Notes</label>
                            <input type="text" name="scan_id" class="form-control" value="{{ old('scan_id', $lead->scan_id) }}" placeholder="Enter ID scan info or link">
                        </div>

                        <div class="input-group input-group-static mb-4">
                            <label>Internal Notes</label>
                            <textarea name="notes" class="form-control" rows="4" placeholder="Enter any additional notes here...">{{ old('notes', $lead->notes) }}</textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('leads.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
