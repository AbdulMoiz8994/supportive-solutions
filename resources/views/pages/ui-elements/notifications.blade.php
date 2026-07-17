@extends('layouts.app')

@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Notifications" />

    <div class="space-y-5 sm:space-y-6">
        <x-common.component-card title="Announcement Bar">
            <x-ui.notification.announcement-bar title="All Done! Success Confirmed"
                message="Your account setup is complete. You can now explore the dashboard." laterButtonText="Close"
                updateButtonText="Go to Dashboard" onLater="closeNotice" onUpdate="redirectToDashboard" />
        </x-common.component-card>

        <x-common.component-card title="Toast Notification">
            <x-ui.notification.toast-notification />
        </x-common.component-card>

        <x-common.component-card title="Success Notification">
            <x-ui.notification.notification type="success" message="Success! Action Completed!" />
        </x-common.component-card>
        <x-common.component-card title="Info Notification">
            <x-ui.notification.notification type="info" message="Heads Up! New Information" />
        </x-common.component-card>
        <x-common.component-card title="Warning Notification">
            <x-ui.notification.notification type="warning" message="Alert: Double Check Required" />
        </x-common.component-card>
        <x-common.component-card title="Error Notification">
            <x-ui.notification.notification type="error" message="Something Went Wrong" />
        </x-common.component-card>
    </div>
@endsection
