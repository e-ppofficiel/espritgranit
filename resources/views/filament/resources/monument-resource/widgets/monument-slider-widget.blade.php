<x-filament-widgets::widget>
    <div id="monument-slider-wrapper"
         x-data="{
            index: 0,
            total: {{ $this->getMonuments()->count() }},
            urls: @js($this->getMonuments()->pluck('id')->map(fn($id) => \App\Filament\Pages\Configurateur::getUrl(['monument' => $id]))->values()),
            prev() { this.index = (this.index - 1 + this.total) % this.total; },
            next() { this.index = (this.index + 1) % this.total; },
            goTo(i) { this.index = i; },
            positionOf(i) {
                let diff = i - this.index;
                if (diff > this.total / 2) diff -= this.total;
                if (diff < -this.total / 2) diff += this.total;
                return diff;
            }
         }">
        <style>
            #monument-slider-wrapper {
                position: relative;
                height: 340px;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                padding: 20px 0 40px;
            }
            .monument-slide {
                position: absolute;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-decoration: none;
                transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.4s ease, filter 0.4s ease;
                cursor: pointer;
            }
            .monument-slide img {
                width: 220px;
                height: 220px;
                object-fit: cover;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.4);
                -webkit-box-reflect: below 2px linear-gradient(transparent, transparent 60%, rgba(255,255,255,0.15));
            }
            .monument-slide span {
                margin-top: 14px;
                font-size: 0.85rem;
                font-weight: 600;
                letter-spacing: 0.5px;
                color: rgb(var(--gray-700, 55 65 81));
                text-transform: uppercase;
            }
            .dark .monument-slide span {
                color: rgb(var(--gray-200, 229 231 235));
            }
            .monument-slider-nav {
                position: absolute;
                top: 45%;
                transform: translateY(-50%);
                background: rgba(0,0,0,0.5);
                color: #fff;
                border: none;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 1.1rem;
                z-index: 10;
            }
            .monument-slider-nav:hover {
                background: rgba(0,0,0,0.75);
            }
            #monument-slider-prev { left: 12px; }
            #monument-slider-next { right: 12px; }
        </style>

        <template x-if="total > 0">
            <button type="button" @click="prev()" class="monument-slider-nav" id="monument-slider-prev">‹</button>
        </template>
        <template x-if="total > 0">
            <button type="button" @click="next()" class="monument-slider-nav" id="monument-slider-next">›</button>
        </template>

        @foreach($this->getMonuments() as $i => $monument)
            <a href="{{ \App\Filament\Pages\Configurateur::getUrl(['monument' => $monument->id]) }}"
               class="monument-slide"
               x-show="Math.abs(positionOf({{ $i }})) <= 1"
               x-bind:style="`
                    transform: translateX(${positionOf({{ $i }}) * 200}px) scale(${positionOf({{ $i }}) === 0 ? 1 : 0.65});
                    z-index: ${positionOf({{ $i }}) === 0 ? 10 : 5};
                    opacity: ${Math.abs(positionOf({{ $i }})) <= 1 ? 1 : 0};
                    filter: ${positionOf({{ $i }}) === 0 ? 'none' : 'brightness(0.6)'};
               `"
               @click.prevent="if (positionOf({{ $i }}) !== 0) { goTo({{ $i }}); } else { window.location.href = urls[{{ $i }}]; }">
                <img src="{{ asset('storage/' . $monument->image_principale) }}" alt="{{ $monument->nom }}">
                <span>{{ $monument->nom }}</span>
            </a>
        @endforeach

        @if($this->getMonuments()->isEmpty())
            <p class="text-sm text-gray-400">Aucun monument actif avec image principale à afficher.</p>
        @endif
    </div>
</x-filament-widgets::widget>
