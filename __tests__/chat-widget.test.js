const { mergeChatOptions } = require('../assets/js/tecai-ally-chat');

describe('TecAI Ally chat widget configuration', () => {
  test('mergeChatOptions applies user overrides', () => {
    const defaults = { position: 'right', accentColor: '#1d4ed8' };
    const overrides = { position: 'left' };

    const result = mergeChatOptions(defaults, overrides);

    expect(result.position).toBe('left');
    expect(result.accentColor).toBe('#1d4ed8');
  });
});
