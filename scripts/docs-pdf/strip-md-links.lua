function Link(el)
    local target = el.target or ""

    if target:match("%.md$") or target:match("%.md#") then
        return pandoc.Span(el.content)
    end

    return el
end
