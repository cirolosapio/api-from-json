# Magento Api from JSON

This service creates classes and interfaces from a JSON file

## Example Usage

### With body

```sh
mkdir -p app/code && curl -d '{"hello": "world"}' "https://drsdu2lnoa.execute-api.eu-central-1.amazonaws.com?namespace=Vendor/Module" | tar xzC app/code
```
  
### From file

```sh
mkdir -p app/code && curl -d @<path/to/json> "https://drsdu2lnoa.execute-api.eu-central-1.amazonaws.com?namespace=Vendor/Module" | tar xzC app/code
```

assuming that `example.json` is in the current folder, the command would be:

```sh
mkdir -p app/code && curl -d @example.json "https://drsdu2lnoa.execute-api.eu-central-1.amazonaws.com?namespace=Vendor/Module" | tar xzC app/code
```

## Parameters (optional)

| name         | description                                     | default         |
| ------------ | ----------------------------------------------- | --------------- |
| `namespace`  | The namespace of the module                     | `Vendor/Module` |
| `first_name` | The name of the class/interface to be generated | `Payload`       |

## Roadmap

- [ ] add tests
- [ ] generate/update di.xml
- [ ] generate/update webapi.xml