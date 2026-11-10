/**
 * <ai-labeling-guide> – live assessment in the "AI" tab of the TYPO3 backend form.
 *
 * Mirrors \FourViewture\AiLabeling\Service\LabelEvaluator (authoritative, executed on save).
 * Keep both implementations in sync.
 */
const INVOLVEMENT = { NONE: 0, ASSISTED: 1, MODIFIED: 2, GENERATED: 3 };
const MODE = { AUTO: 0, FORCE: 1, SUPPRESS: 2 };
const STATUS = { NONE: 0, OPTIONAL: 1, RECOMMENDED: 2, REQUIRED: 3 };
const SEVERITY = { 0: 'notice', 1: 'info', 2: 'warning', 3: 'danger' };

export function evaluate(v) {
  const reasons = [];
  let status = STATUS.NONE;

  if (v.involvement === INVOLVEMENT.NONE) {
    reasons.push('none');
  } else if (v.involvement === INVOLVEMENT.ASSISTED) {
    reasons.push('assisted');
  } else {
    if (v.realReference && v.deceptive) reasons.push('deepfake');
    if (v.syntheticPerson) reasons.push('syntheticPerson');
    if (v.productAltered) reasons.push('productAltered');
    if (v.publicInterest && !v.humanReview) reasons.push('publicInterestText');

    if (reasons.length > 0) {
      status = STATUS.REQUIRED;
    } else if (v.realReference !== v.deceptive) {
      status = STATUS.RECOMMENDED;
      reasons.push('doubt');
    } else if (v.publicInterest && v.humanReview) {
      status = STATUS.OPTIONAL;
      reasons.push('reviewedText');
    } else {
      status = STATUS.OPTIONAL;
      reasons.push('noCriteria');
    }
  }

  let active = status >= STATUS.RECOMMENDED;
  if (v.mode === MODE.FORCE) {
    active = true;
    reasons.push('forced');
  } else if (v.mode === MODE.SUPPRESS) {
    active = false;
    reasons.push(status === STATUS.REQUIRED ? 'suppressedRequired' : 'suppressed');
  }

  const labelType = active ? (v.involvement === INVOLVEMENT.MODIFIED ? 'modified' : 'generated') : null;
  return { status, active, reasons, labelType };
}

const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (c) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

class AiLabelingGuide extends HTMLElement {
  connectedCallback() {
    this.prefix = this.dataset.prefix || '';
    this.labels = JSON.parse(this.dataset.labels || '{}');
    this.form = this.closest('form') || document;
    this.scheduled = false;
    this.onChange = () => this.schedule();
    this.form.addEventListener('change', this.onChange);
    this.form.addEventListener('input', this.onChange);
    this.schedule();
  }

  disconnectedCallback() {
    this.form?.removeEventListener('change', this.onChange);
    this.form?.removeEventListener('input', this.onChange);
  }

  schedule() {
    if (this.scheduled) return;
    this.scheduled = true;
    // FormEngine updates its hidden fields in its own change listeners – read afterwards
    window.setTimeout(() => {
      this.scheduled = false;
      this.render();
    }, 0);
  }

  fieldName(field) {
    return `${this.prefix}[${field}]`;
  }

  readInt(field) {
    const name = CSS.escape(this.fieldName(field));
    const toggle = this.form.querySelector(`input[type="checkbox"][data-formengine-input-name="${name}"]`);
    if (toggle) return toggle.checked ? 1 : 0;
    const element = this.form.querySelector(`[name="${name}"]`);
    if (!element) return 0;
    return parseInt(element.value, 10) || 0;
  }

  readString(field) {
    const name = CSS.escape(this.fieldName(field));
    const element = this.form.querySelector(`[data-formengine-input-name="${name}"]`)
      || this.form.querySelector(`[name="${name}"]`);
    return element ? String(element.value || '').trim() : '';
  }

  values() {
    return {
      involvement: this.readInt('tx_ailabeling_involvement'),
      mode: this.readInt('tx_ailabeling_mode'),
      realReference: this.readInt('tx_ailabeling_real_reference') === 1,
      deceptive: this.readInt('tx_ailabeling_deceptive') === 1,
      syntheticPerson: this.readInt('tx_ailabeling_synthetic_person') === 1,
      productAltered: this.readInt('tx_ailabeling_product_altered') === 1,
      publicInterest: this.readInt('tx_ailabeling_public_interest') === 1,
      humanReview: this.readInt('tx_ailabeling_human_review') === 1,
    };
  }

  render() {
    const target = this.querySelector('[data-role="status"]');
    if (!target) return;
    const l = this.labels;
    const result = evaluate(this.values());

    const reasons = result.reasons
      .map((reason) => `<li>${escapeHtml(l.reasons?.[reason] ?? reason)}</li>`)
      .join('');

    let suggestion = '';
    if (result.active && result.labelType) {
      const text = this.readString('tx_ailabeling_label_text') || l.defaultText?.[result.labelType] || '';
      suggestion = `<p class="ai-labeling-guide__suggestion">${escapeHtml(l.suggestion)}: `
        + `<span class="badge badge-${result.labelType === 'modified' ? 'info' : 'danger'}">${escapeHtml(l.labelType?.[result.labelType] ?? '')}</span> `
        + `„${escapeHtml(text)}“</p>`;
    }

    target.innerHTML = `
      <div class="callout callout-${SEVERITY[result.status]}">
        <div class="callout-content">
          <div class="callout-title">${escapeHtml(l.statusTitle)}: ${escapeHtml(l.status?.[result.status] ?? '')}</div>
          <div class="callout-body">
            <ul class="ai-labeling-guide__reasons">${reasons}</ul>
            <p><strong>${escapeHtml(l.active?.[result.active ? 1 : 0] ?? '')}</strong></p>
            ${suggestion}
          </div>
        </div>
      </div>`;
  }
}

if (!customElements.get('ai-labeling-guide')) {
  customElements.define('ai-labeling-guide', AiLabelingGuide);
}
