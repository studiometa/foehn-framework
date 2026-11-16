### Added

- The `foehn/template_controller/skip` filter skips the template controllers for one request: return `true` and WordPress includes the template file that it chose. It receives `false`, the template name and the class name of the controller. Use it in place of removing `TemplateControllerDiscovery::handleTemplateInclude` from `template_include`
