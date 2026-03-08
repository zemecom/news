function CodeBlock(el)
    for _, class in ipairs(el.classes) do
        if class == "mermaid" then
            local attr = pandoc.Attr("", {"mermaid-panel"}, {})
            local label = pandoc.Div(
                {pandoc.Para({pandoc.Str("Diagram (Mermaid source)")})},
                pandoc.Attr("", {"mermaid-label"}, {})
            )
            local code = pandoc.CodeBlock(el.text, pandoc.Attr("", {"diagram-source"}, {}))

            return pandoc.Div({label, code}, attr)
        end
    end

    return el
end
