@php
    /** @var \Illuminate\Support\Collection $menus */
    $editable = $editable ?? false;
    $moduleKey = $moduleKey ?? null;
    $moduleCore = $moduleCore ?? true;
@endphp
<div class="menu-table">
    <table>
        <thead>
            <tr>
                <th>Menu</th>
                <th class="menu-col-switch">{{ $editable ? 'On / Off' : 'Status' }}</th>
                <th class="menu-col-link">Link</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($menus as $menu)
                <tr x-data="{ show: false }">
                    <td>
                        <span class="menu-cell">
                            <span class="menu-ico"><x-icon :name="$menu['icon'] ?? 'circle'" :size="14" /></span>
                            <span class="menu-name">{{ $menu['name'] }}</span>
                        </span>
                    </td>

                    <td class="menu-col-switch">
                        @if ($menu['core'])
                            <span class="menu-always"><x-icon name="check" :size="11" /> On</span>
                        @elseif ($editable)
                            <input type="hidden" name="menu_scope[]" value="{{ $menu['key'] }}"
                                   @if (! $moduleCore) x-bind:disabled="!enabled.includes('{{ $moduleKey }}')" @endif>
                            <span class="menu-switch-wrap"
                                  @if (! $moduleCore) x-bind:class="!enabled.includes('{{ $moduleKey }}') && 'is-disabled'" @endif>
                                <label class="menu-switch" title="Off keeps this menu visible but locked for the school">
                                    <input type="checkbox" name="menus[]" value="{{ $menu['key'] }}" x-model="menus"
                                           @if (! $moduleCore) x-bind:disabled="!enabled.includes('{{ $moduleKey }}')" @endif>
                                    <span class="menu-track" aria-hidden="true"></span>
                                </label>
                                <span class="menu-state"
                                      :class="menus.includes('{{ $menu['key'] }}') ? 'is-on' : 'is-off'"
                                      x-text="menus.includes('{{ $menu['key'] }}') ? 'On' : 'Off'"></span>
                            </span>
                        @else
                            <span class="menu-state {{ $menu['enabled'] ? 'is-on' : 'is-off' }}">
                                {{ $menu['enabled'] ? 'On' : 'Off' }}
                            </span>
                        @endif
                    </td>

                    <td class="menu-col-link">
                        @if ($menu['url'])
                            <button type="button" class="menu-link-btn" @click="show = !show"
                                    x-text="show ? 'Hide link' : 'Show link'"></button>
                            <div x-show="show" x-cloak class="menu-link-box">
                                <code>{{ $menu['url'] }}</code>
                                <a href="{{ $menu['url'] }}" target="_blank" rel="noopener">Open ↗</a>
                            </div>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="table-empty">No menus in this module yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
