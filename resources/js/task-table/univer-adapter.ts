export type TaskTableDocument = Record<string, unknown>;

export const createEmptyTaskTableDocument = (): TaskTableDocument => ({
    id: crypto.randomUUID(),
    name: "Tabela",
    appVersion: "0.25.1",
    locale: "ptBR",
    styles: {},
    sheetOrder: ["sheet-1"],
    sheets: {
        "sheet-1": {
            id: "sheet-1",
            name: "Tabela",
            rowCount: 5,
            columnCount: 5,
            cellData: {},
            rowData: {},
            columnData: {},
            mergeData: [],
        },
    },
});

export interface MountedTaskTable {
    snapshot(): TaskTableDocument;
    dispose(): void;
}

export async function mountTaskTable(
    container: HTMLElement,
    document: TaskTableDocument,
    readOnly = false,
): Promise<MountedTaskTable> {
    const [
        core,
        coreFacade,
        docs,
        docsUi,
        sheets,
        sheetsFacade,
        ui,
        sheetsUi,
        formula,
        formulaUi,
        uiLocale,
        sheetsLocale,
        sheetsUiLocale,
        formulaLocale,
        formulaUiLocale,
    ] = await Promise.all([
        import("@univerjs/core"),
        import("@univerjs/core/facade"),
        import("@univerjs/docs"),
        import("@univerjs/docs-ui"),
        import("@univerjs/sheets"),
        import("@univerjs/sheets/facade"),
        import("@univerjs/ui"),
        import("@univerjs/sheets-ui"),
        import("@univerjs/sheets-formula"),
        import("@univerjs/sheets-formula-ui"),
        import("@univerjs/ui/locale/pt-BR"),
        import("@univerjs/sheets/locale/pt-BR"),
        import("@univerjs/sheets-ui/locale/pt-BR"),
        import("@univerjs/sheets-formula/locale/pt-BR"),
        import("@univerjs/sheets-formula-ui/locale/pt-BR"),
        import("@univerjs/ui/lib/index.css"),
        import("@univerjs/docs-ui/lib/index.css"),
        import("@univerjs/sheets-ui/lib/index.css"),
    ]);
    void sheetsFacade;
    const locales = {
        [core.LocaleType.PT_BR]: {
            ...uiLocale.default,
            ...sheetsLocale.default,
            ...sheetsUiLocale.default,
            ...formulaLocale.default,
            ...formulaUiLocale.default,
        },
    };
    const univer = new core.Univer({ locale: core.LocaleType.PT_BR, locales });
    univer.registerPlugin(docs.UniverDocsPlugin);
    univer.registerPlugin(sheets.UniverSheetsPlugin);
    univer.registerPlugin(ui.UniverUIPlugin, {
        container,
        header: !readOnly,
        footer: false,
        toolbar: !readOnly,
        contextMenu: !readOnly,
        disableAutoFocus: true,
    });
    univer.registerPlugin(docsUi.UniverDocsUIPlugin, { container, footer: false });
    univer.registerPlugin(sheetsUi.UniverSheetsUIPlugin);
    univer.registerPlugin(formula.UniverSheetsFormulaPlugin);
    univer.registerPlugin(formulaUi.UniverSheetsFormulaUIPlugin);
    const univerApi = coreFacade.FUniver.newAPI(univer);
    const workbook = univerApi.createWorkbook(document as never);
    const permissions = workbook.getWorkbookPermission();
    if (readOnly) {
        await permissions.setReadOnly();
    } else {
        await Promise.all([
            "CreateSheet", "DeleteSheet", "RenameSheet", "MoveSheet", "DuplicateFile", "Export", "Print", "Share",
        ].map((point) => permissions.setPoint((univerApi.Enum.WorkbookPermissionPoint as Record<string, string>)[point] as never, false)));
    }

    return {
        snapshot: () => workbook.save() as unknown as TaskTableDocument,
        dispose: () => univer.dispose(),
    };
}
