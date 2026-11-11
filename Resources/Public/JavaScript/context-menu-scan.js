/*
 * EXT:ai_labeling – file list context menu "Scan for AI content".
 * 1. preview (like ailabeling:scan --dry-run): evidence + planned changes in a modal
 * 2. "Apply" writes the metadata (same logic as upload detection), then the file list reloads.
 * Callback contract of the context menu (12.4–14.3): default export, method(table, uid, dataset);
 * uid is the combined identifier of the file.
 */
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import Severity from '@typo3/backend/severity.js';

const element = (tag, text, className) => {
  const node = document.createElement(tag);
  if (text !== undefined) node.textContent = text;
  if (className) node.className = className;
  return node;
};

const renderContent = (data) => {
  const content = element('div');
  content.append(element('p', data.message));
  if (data.evidence?.length) {
    content.append(element('h3', data.labels.evidence, 'h5'));
    const list = element('ul');
    data.evidence.forEach((line) => list.append(element('li', line)));
    content.append(list);
  }
  if (data.changes?.length) {
    content.append(element('h3', data.labels.changes, 'h5'));
    const table = element('table', undefined, 'table table-sm');
    const body = element('tbody');
    data.changes.forEach((change) => {
      const row = element('tr');
      row.append(element('th', change.field), element('td', change.from), element('td', '→'), element('td', change.to));
      row.firstChild.setAttribute('scope', 'row');
      body.append(row);
    });
    table.append(body);
    content.append(table);
  }
  return content;
};

const request = async (identifier, apply) => {
  const url = top.TYPO3?.settings?.ajaxUrls?.ailabeling_scan_file ?? TYPO3.settings.ajaxUrls.ailabeling_scan_file;
  try {
    const response = await new AjaxRequest(url).post({ identifier, apply: apply ? 1 : 0 });
    return await response.resolve();
  } catch (error) {
    let data = null;
    try {
      data = await error.response?.json();
    } catch {
      // no JSON body
    }
    Notification.error(data?.message ?? 'AI scan failed', '', 6);
    return null;
  }
};

class ContextMenuScan {
  static async scanFile(table, uid) {
    const data = await request(uid, false);
    if (!data?.success) {
      return;
    }
    const buttons = [{
      text: data.labels.close,
      btnClass: 'btn-default',
      name: 'close',
      trigger: (event, modal) => modal.hideModal(),
    }];
    if (data.canApply) {
      buttons.push({
        text: data.labels.apply,
        btnClass: 'btn-primary',
        name: 'apply',
        active: true,
        trigger: async (event, modal) => {
          modal.hideModal();
          const result = await request(uid, true);
          if (result?.applied) {
            Notification.success(result.title, result.message, 5);
            top.TYPO3?.Backend?.ContentContainer?.refresh?.();
          }
        },
      });
    }
    Modal.advanced({
      title: data.title,
      content: renderContent(data),
      severity: data.found ? Severity.info : Severity.notice,
      buttons,
    });
  }
}

export default ContextMenuScan;
