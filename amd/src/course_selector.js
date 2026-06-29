define(['core/form-autocomplete'], function(Autocomplete) {
  return {
    init: function(selector) {
      const field = document.querySelector(selector);

      if (!field) {
        return;
      }

      Autocomplete.enhanceField(
        selector,
        false,
        '',
        field.dataset.placeholder || 'Search',
        false,
        true,
        'No selection',
        false
      );
    }
  };
});
