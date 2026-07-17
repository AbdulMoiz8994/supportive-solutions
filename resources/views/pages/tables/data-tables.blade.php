 <div className="">
        <ComponentCard title="Data Table 1">
          <DataTableOne />
        </ComponentCard>
        <ComponentCard title="Data Table 2">
          <DataTableTwo />
        </ComponentCard>
        <ComponentCard title="Data Table 3">
          <DataTableThree />
        </ComponentCard>
      </div>

      @extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Data Tables" />
    <div class="space-y-5 sm:space-y-6">
        <x-common.component-card title="Data Table 1">
            <x-tables.data-tables.data-table-one />
        </x-common.component-card>
        <x-common.component-card title="Data Table 2">
            <x-tables.data-tables.data-table-two />
        </x-common.component-card>
        <x-common.component-card title="Data Table 3">
            <x-tables.data-tables.data-table-three />
        </x-common.component-card>
    </div>
@endsection
