<style>
    .tx-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .tx-delete-form { display: inline-flex; margin: 0; }
    .tx-edit-btn, .tx-delete-btn { min-height: 44px; border: none; padding: 8px 12px; border-radius: 8px; color: white; font-size: 13px; font-weight: 700; line-height: 1; cursor: pointer; white-space: nowrap; }
    .tx-edit-btn { background: #4f5fc9; }
    .tx-edit-btn:hover { background: #3f4fae; transform: translateY(-1px); }
    .tx-delete-btn { background: #c93643; }
    .tx-delete-btn:hover { background: #a92733; transform: translateY(-1px); }
    .tx-category { display: inline-flex; align-items: center; gap: 6px; min-width: 0; overflow-wrap: anywhere; }
    .tx-category .color-dot { flex: 0 0 auto; margin-right: 0; }
    .tx-edit-btn:focus-visible, .tx-delete-btn:focus-visible, .tx-close-btn:focus-visible, .tx-save-btn:focus-visible { outline: 3px solid #293b91; outline-offset: 2px; }
    .tx-modal { display: none; position: fixed; inset: 0; padding: 20px; overflow-y: auto; background: rgba(25, 28, 45, 0.62); z-index: 1000; align-items: center; justify-content: center; }
    .tx-modal.active { display: flex; }
    .tx-modal-content { width: min(560px, 100%); max-height: calc(100vh - 40px); max-height: calc(100dvh - 40px); overflow-y: auto; background: white; border-radius: 18px; padding: 28px; box-shadow: 0 24px 70px rgba(0, 0, 0, 0.28); }
    .tx-modal-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 22px; }
    .tx-modal-header h2 { color: #333; font-size: 24px; margin: 0; }
    .tx-close-btn { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 44px; width: 44px; height: 44px; border: none; background: transparent; color: #666; padding: 0; font-size: 30px; line-height: 1; cursor: pointer; }
    .tx-close-btn:hover { color: #222; transform: none; }
    .tx-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .tx-form-group { margin-bottom: 16px; }
    .tx-form-group.full { grid-column: 1 / -1; }
    .tx-form-group label { display: block; margin-bottom: 8px; color: #555; font-size: 14px; }
    .tx-form-group input, .tx-form-group select, .tx-form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid #d7dbf0; border-radius: 10px; background: white; font: inherit; }
    .tx-form-group textarea { min-height: 90px; resize: vertical; }
    .tx-installment-note { display: none; margin: 0 0 18px; padding: 12px 14px; border-radius: 10px; background: #fff7df; color: #795b00; font-size: 13px; line-height: 1.5; }
    .tx-installment-note.visible { display: block; }
    .tx-save-btn { width: 100%; min-height: 44px; border: none; border-radius: 12px; padding: 13px 18px; background: #4f5fc9; color: white; font-size: 15px; font-weight: 700; cursor: pointer; }
    .tx-save-btn:hover { background: #3f4fae; transform: translateY(-1px); }
    @media (max-width: 560px) {
        .transactions-table td[data-label="Ações"] { grid-template-columns: 1fr; text-align: left; }
        .transactions-table .tx-category { justify-self: end; text-align: right; }
        .tx-actions { display: grid; grid-template-columns: 1fr 1fr; width: 100%; }
        .tx-delete-form { display: flex; }
        .tx-edit-btn, .tx-delete-btn { width: 100%; min-height: 44px; }
        .tx-modal-content { padding: 22px 18px; }
        .tx-form-grid { grid-template-columns: 1fr; gap: 0; }
        .tx-form-group.full { grid-column: auto; }
    }
</style>
