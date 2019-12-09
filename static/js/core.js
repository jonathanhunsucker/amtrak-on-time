class Defaults {
  static get blue() {
    return 'rgba(0, 0, 255, 0.8)';
  }
  static get translucentBlue() {
    return 'rgba(0, 0, 255, 0.1)';
  }
  static get red() {
    return 'rgba(255, 0, 0, 0.8)';
  }
}

class Functional {
  static get unique() {
    return (accumulation, item) => {
      return accumulation.indexOf(item) === -1 ? accumulation.concat(item) : accumulation;
    };
  }
}
