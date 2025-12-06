(function () {
  const { registerBlockType } = window.wp.blocks || {};
  const { __ } = window.wp.i18n || {};
  const { createElement: el } = window.wp.element || {};

  if (!registerBlockType || !el) {
    return;
  }

  registerBlockType('tecai-ally/chat', {
    title: __('TecAI Ally Chat', 'tecai-ally'),
    icon: 'format-chat',
    category: 'widgets',
    edit: function Edit() {
      return el(
        'p',
        {},
        __('TecAI Ally chat bubble will appear on the frontend. No additional configuration needed here.', 'tecai-ally')
      );
    },
    save: function Save() {
      return null;
    },
  });
})();
