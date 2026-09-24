<x-mail::message :manage-notifications-url="$manageNotificationsUrl ?? null" :unsubscribe-url="$unsubscribeUrl ?? null">
{{-- Greeting --}}
# @lang('Hello!')

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Totals --}}
<x-mail::table>
| @lang('Currency') | @lang('Income') | @lang('Expenses') | @lang('Net') |
| :-- | --: | --: | --: |
@foreach ($totals as $total)
| {{ $total['currency'] }} | {{ $total['income'] }} | {{ $total['expenses'] }} | {{ $total['net'] }} |
@endforeach
</x-mail::table>

{{-- Top Categories --}}
@foreach ($categories as $section)
**{{ __('Top categories in :currency', ['currency' => $section['currency']]) }}**

<x-mail::table>
| @lang('Category') | @lang('Spent') |
| :-- | --: |
@foreach ($section['rows'] as $row)
| {{ $row['category'] }} | {{ $row['spent'] }} |
@endforeach
</x-mail::table>

@endforeach
{{-- Budgets --}}
@if ($budgets !== [])
**@lang('Budgets')**

<x-mail::table>
| @lang('Budget') | @lang('Amount') | @lang('Spent') | @lang('Outcome') |
| :-- | --: | --: | :-- |
@foreach ($budgets as $budget)
| {{ $budget['name'] }} | {{ $budget['amount'] }} | {{ $budget['spent'] }} | {{ $budget['outcome'] }} |
@endforeach
</x-mail::table>

@endif
{{-- Action Button --}}
<x-mail::button :url="$actionUrl" color="primary">
{{ $actionText }}
</x-mail::button>

{{-- Salutation --}}
@lang('Regards,')<br>
{{ config('app.name') }}

{{-- Subcopy --}}
<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
