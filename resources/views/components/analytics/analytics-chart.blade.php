<div
  class="rounded-2xl border border-gray-200 bg-white px-5 pt-5 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6 sm:pt-6"
>
  <div class="flex flex-wrap items-start justify-between gap-5">
    <div>
      <h3 class="mb-1 text-lg font-semibold text-gray-800 dark:text-white/90">
        Analytics
      </h3>
      <span class="block text-theme-sm text-gray-500 dark:text-gray-400">
        Visitor analytics of last 30 days
      </span>
    </div>

    <div
      x-data="{selected: 'optionOne'}"
      class="flex items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900"
    >
      @php
          $timeFrames = [
              [
                  'value' => 'optionOne',
                  'label' => '12 months'
              ],
              [
                  'value' => 'optionTwo',
                  'label' => '30 days'
              ],
              [
                  'value' => 'optionThree',
                  'label' => '7 days'
              ],
              [
                  'value' => 'optionFour',
                  'label' => '24 hours'
              ]
          ];
      @endphp

      @foreach($timeFrames as $timeFrame)
          <button
              @click="selected = '{{ $timeFrame['value'] }}'"
              :class="selected === '{{ $timeFrame['value'] }}' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
              class="px-3 py-2 font-medium rounded-md text-theme-sm"
          >
              {{ $timeFrame['label'] }}
          </button>
      @endforeach
    </div>
  </div>
  <div class="custom-scrollbar max-w-full overflow-x-auto">
    <div id="chartFour" class="-ml-5 min-w-[1300px] pl-2"></div>
  </div>
</div>
