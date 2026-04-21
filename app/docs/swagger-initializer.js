window.onload = function () {
  window.ui = SwaggerUIBundle({
    url: "/openapi.yaml",
    dom_id: "#swagger-ui",
    persistAuthorization: true,
    displayRequestDuration: true,
    tryItOutEnabled: true,
    filter: true,
    docExpansion: "list",
    defaultModelsExpandDepth: -1,
    deepLinking: true,
    presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
    plugins: [SwaggerUIBundle.plugins.DownloadUrl],
    layout: "StandaloneLayout",
    requestInterceptor: function (req) {
      if (!req.headers["Accept"] && !req.headers["accept"]) {
        req.headers["Accept"] = "application/json";
      }
      return req;
    },
    tagsSorter: function (a, b) {
      var order = ["Auth", "Info", "Problems", "Reporter", "Internal"];
      function tagName(x) {
        if (typeof x === "string") return x;
        if (x && typeof x.get === "function") return x.get("name");
        return String(x);
      }
      var an = tagName(a);
      var bn = tagName(b);
      var ai = order.indexOf(an);
      var bi = order.indexOf(bn);
      if (ai === -1) ai = 999;
      if (bi === -1) bi = 999;
      if (ai !== bi) return ai - bi;
      return String(an).localeCompare(String(bn));
    },
    operationsSorter: function (a, b) {
      function opField(x, field) {
        if (x && typeof x.get === "function") return x.get(field);
        return x && x[field];
      }
      var am = opField(a, "path");
      var bm = opField(b, "path");
      if (am !== bm) return String(am).localeCompare(String(bm));
      return String(opField(a, "method")).localeCompare(String(opField(b, "method")));
    },
  });
};
