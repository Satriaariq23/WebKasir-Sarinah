(function (global) {
    'use strict';

    const finiteOrZero = value => Number.isFinite(value) ? value : 0;

    // Nilai API/database memakai titik sebagai desimal: "1950.000000" = 1950.
    function fromApi(value) {
        if (typeof value === 'number') return finiteOrZero(value);
        if (value === null || value === undefined || value === '') return 0;

        const text = String(value).trim().replace(/\s+/g, '');
        if (/^[+-]?\d+(?:\.\d+)?$/.test(text)) {
            return finiteOrZero(Number(text));
        }
        return fromLocale(text);
    }

    // Input Indonesia: 1.950,50 = 1950.5 dan 900,0000 = 900.
    function fromLocale(value) {
        if (typeof value === 'number') return finiteOrZero(value);
        if (value === null || value === undefined || value === '') return 0;

        let text = String(value).trim();
        const firstDigitIndex = text.search(/\d/);
        const minusIndex = text.indexOf('-');
        const negative = /^\s*\(.*\)\s*$/.test(text)
            || (minusIndex >= 0 && (firstDigitIndex < 0 || minusIndex < firstDigitIndex));
        text = text.replace(/[^0-9.,]/g, '');
        if (!text) return 0;

        const lastComma = text.lastIndexOf(',');
        const lastDot = text.lastIndexOf('.');
        if (lastComma >= 0 && lastDot >= 0) {
            text = lastComma > lastDot
                ? text.replace(/\./g, '').replace(',', '.')
                : text.replace(/,/g, '');
        } else if (lastComma >= 0) {
            text = text.replace(/\./g, '').replace(',', '.');
        } else if (/^\d{1,3}(?:\.\d{3})+$/.test(text)) {
            text = text.replace(/\./g, '');
        }

        const number = finiteOrZero(Number(text));
        return negative ? -Math.abs(number) : number;
    }

    function formatMoney(value, options) {
        const settings = Object.assign({
            prefix: false,
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }, options || {});
        const formatted = fromApi(value).toLocaleString('id-ID', {
            minimumFractionDigits: settings.minimumFractionDigits,
            maximumFractionDigits: settings.maximumFractionDigits
        });
        return settings.prefix ? 'Rp ' + formatted : formatted;
    }

    function formatQuantity(value, options) {
        const settings = Object.assign({
            minimumFractionDigits: 0,
            maximumFractionDigits: 4
        }, options || {});
        return fromApi(value).toLocaleString('id-ID', settings);
    }

    function roundMoney(value) {
        return Math.round((fromApi(value) + Number.EPSILON) * 100) / 100;
    }

    global.SarinahNumbers = Object.freeze({
        fromApi,
        fromLocale,
        parseMoney: fromLocale,
        parseApiQuantity: fromApi,
        parseLocaleQuantity: fromLocale,
        // Alias lama: quantity berformat tampilan Indonesia, bukan nilai API.
        parseQuantity: fromLocale,
        formatMoney,
        formatQuantity,
        roundMoney
    });
})(window);
