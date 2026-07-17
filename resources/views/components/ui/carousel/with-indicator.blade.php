@php
    $carouselData = [
        ['thumbnail' => '/images/carousel/carousel-01.png'],
        ['thumbnail' => '/images/carousel/carousel-02.png'],
        ['thumbnail' => '/images/carousel/carousel-03.png'],
        ['thumbnail' => '/images/carousel/carousel-04.png'],
    ];
@endphp

<div class="relative border border-gray-200 rounded-lg carouselThree dark:border-gray-800">
  <swiper-container slides-per-view="1" autoplay-delay="5000" autoplay-disable-on-interaction="false" pagination-clickable="true" pagination-el=".swiper-pagination-one">
    <!-- slider item -->
    @foreach ($carouselData as $item)
    <swiper-slide>
      <div class="overflow-hidden rounded-lg">
        <img src="{{ $item['thumbnail'] }}" class="w-full rounded-lg" alt="carousel" />
      </div>
    </swiper-slide>
    @endforeach
  </swiper-container>
  <!-- If we need pagination -->
  <div class="swiper-pagination swiper-pagination-one"></div>
</div>
