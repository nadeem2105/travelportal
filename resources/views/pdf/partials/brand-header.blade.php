{{-- Brand header block shared by all PDF documents --}}
@php($companyName = settings('company_name', 'Leemroz Travels'))
<table class="w">
    <tr>
        <td class="vtop">
            <div class="brand-name">{{ $companyName }}</div>
            <div class="brand-tagline">{{ strtoupper(settings('company_tagline', 'EXPLORE · BOOK · EXPERIENCE')) }}</div>
            <div class="brand-line">{{ settings('company_address', 'Ishber Nishat Gupt Ganga, Srinagar J&K -190025') }}</div>
            <div class="brand-line muted">
                Helpline: {{ settings('company_phone', '') }}
            </div>
        </td>
        <td class="vtop doc-right">
            @yield('doc_badge')
            @yield('doc_right')
        </td>
    </tr>
</table>
<div class="head-rule"></div>
